<?php

namespace App\Legal;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ConflictCheck;
use App\Models\Matter;
use App\Models\MatterParty;
use App\Models\User;
use App\Support\NameNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Searches clients, client contacts and matter parties (including opposing
 * parties) for names related to a new matter. Searches ALL records, including
 * restricted matters, because a conflict hidden by an ethical wall is still a
 * conflict. Results about restricted matters are redacted to reference + lead.
 *
 * It only proposes candidates; a reviewer decides (spec §7).
 */
class ConflictSearch
{
    /** similar_text percentage at or above which a non-exact name is reported. */
    public const SIMILARITY_THRESHOLD = 80;

    /**
     * @param  list<array{name: string, role: string}>  $parties
     */
    public function run(array $parties, User $by, ?Client $client = null, ?Matter $matter = null): ConflictCheck
    {
        if (! Gate::forUser($by)->allows('create', ConflictCheck::class)) {
            throw new AuthorizationException('You may not run conflict checks.');
        }

        $parties = collect($parties)
            ->map(fn ($p) => ['name' => trim((string) ($p['name'] ?? '')), 'role' => $p['role'] ?? 'other'])
            ->filter(fn ($p) => NameNormalizer::normalize($p['name']) !== '')
            ->unique(fn ($p) => NameNormalizer::normalize($p['name']).'|'.$p['role'])
            ->values();

        if ($parties->isEmpty()) {
            throw ValidationException::withMessages(['parties' => 'Enter at least one name to search.']);
        }

        $hits = $parties->flatMap(fn ($party) => $this->searchName($party['name'], $party['role'], $client, $matter))
            ->unique(fn ($hit) => $hit['source'].':'.$hit['record_id'].':'.$hit['searched'])
            ->values();

        return DB::transaction(function () use ($parties, $hits, $by, $client, $matter) {
            $check = ConflictCheck::create([
                'client_id' => $client?->id,
                'matter_id' => $matter?->id,
                'requested_by' => $by->id,
                'status' => 'pending_review',
                'results' => ['hits' => $hits->all(), 'searched_at' => now()->toIso8601String()],
            ]);
            foreach ($parties as $party) {
                $check->parties()->create($party);
            }

            ActivityLog::record('conflict.run', $check, "Conflict check {$check->reference}: {$hits->count()} potential match(es)", userId: $by->id);

            if ($matter && $matter->status === 'intake') {
                app(MatterLifecycle::class)->transition($matter, 'conflict_review', $by, "Conflict check {$check->reference} run");
            }

            return $check;
        });
    }

    public function review(ConflictCheck $check, User $by, string $decision, string $notes): ConflictCheck
    {
        if (! Gate::forUser($by)->allows('review', $check)) {
            throw new AuthorizationException('You may not review this conflict check.');
        }
        if (! in_array($decision, ['cleared', 'conflict_found', 'waived'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose a valid decision.']);
        }
        if (trim($notes) === '') {
            throw ValidationException::withMessages(['notes' => 'Record the reasoning for this decision.']);
        }
        if ($decision === 'cleared' && $check->hitCount() > 0 && mb_strlen(trim($notes)) < 20) {
            throw ValidationException::withMessages(['notes' => 'Explain why each potential match is not a conflict.']);
        }

        $check->forceFill(['status' => $decision, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'review_notes' => $notes])->save();
        ActivityLog::record('conflict.reviewed', $check, "Conflict check {$check->reference}: ".ConflictCheck::STATUSES[$decision], userId: $by->id);

        return $check;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function searchName(string $name, string $role, ?Client $exceptClient, ?Matter $exceptMatter): Collection
    {
        $normalized = NameNormalizer::normalize($name);
        $tokens = NameNormalizer::tokens($normalized);

        $candidates = fn ($query, string $column) => $query->where(function ($q) use ($column, $normalized, $tokens) {
            $q->where($column, $normalized);
            foreach ($tokens as $token) {
                $q->orWhere($column, 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $token).'%');
            }
        });

        $hits = collect();

        foreach ($candidates(Client::withTrashed(), 'normalized_name')->when($exceptClient, fn ($q) => $q->whereKeyNot($exceptClient->id))->limit(200)->get() as $c) {
            if ($score = $this->score($normalized, $c->normalized_name)) {
                $hits->push($this->hit('client', $c->id, $c->name, $name, $role, $score, [
                    'relationship' => 'Client ('.Client::STATUSES[$c->status].')',
                    'client_reference' => $c->reference,
                ]));
            }
        }

        foreach ($candidates(ClientContact::query()->with('client'), 'normalized_name')->limit(200)->get() as $contact) {
            if ($contact->client_id === $exceptClient?->id) {
                continue;
            }
            if ($score = $this->score($normalized, $contact->normalized_name)) {
                $hits->push($this->hit('client_contact', $contact->id, $contact->name, $name, $role, $score, [
                    'relationship' => 'Contact/representative of client '.$contact->client?->reference,
                    'client_reference' => $contact->client?->reference,
                ]));
            }
        }

        foreach ($candidates(MatterParty::query()->with('matter.lead'), 'normalized_name')->limit(200)->get() as $party) {
            if ($party->matter_id === $exceptMatter?->id || ! $party->matter) {
                continue;
            }
            if ($score = $this->score($normalized, $party->normalized_name)) {
                $hits->push($this->hit('matter_party', $party->id, $party->name, $name, $role, $score, [
                    'relationship' => MatterParty::TYPES[$party->party_type] ?? $party->party_type,
                ] + $this->matterInfo($party->matter)));
            }
        }

        // Existing clients appearing as clients in other matters are covered via the client hit;
        // also flag matters whose CLIENT matches (for context on which matters are involved).
        return $hits;
    }

    /** Restricted matters: reveal only that a match exists, its reference and whom to ask. */
    private function matterInfo(Matter $matter): array
    {
        return $matter->is_restricted
            ? ['matter_reference' => $matter->reference, 'matter_title' => 'Restricted matter', 'restricted' => true, 'contact' => $matter->lead?->name]
            : ['matter_reference' => $matter->reference, 'matter_title' => $matter->title, 'restricted' => false, 'matter_status' => $matter->statusLabel()];
    }

    private function score(string $searched, ?string $candidate): ?int
    {
        if ($candidate === null || $candidate === '') {
            return null;
        }
        if ($candidate === $searched) {
            return 100;
        }
        similar_text($searched, $candidate, $percent);
        // Also catch "jane namukasa" vs "namukasa jane" (token order).
        $sortedA = collect(explode(' ', $searched))->sort()->implode(' ');
        $sortedB = collect(explode(' ', $candidate))->sort()->implode(' ');
        if ($sortedA === $sortedB) {
            return 95;
        }

        return $percent >= self::SIMILARITY_THRESHOLD ? (int) round($percent) : null;
    }

    private function hit(string $source, int $id, string $matchedName, string $searched, string $role, int $score, array $extra): array
    {
        return [
            'source' => $source, 'record_id' => $id, 'matched_name' => $matchedName,
            'searched' => $searched, 'searched_role' => $role, 'score' => $score,
            'match' => $score === 100 ? 'exact' : 'similar',
        ] + $extra;
    }
}
