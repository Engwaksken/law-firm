<?php

namespace App\Legal;

use App\Models\ActivityLog;
use App\Models\Engagement;
use App\Models\Matter;
use App\Models\User;
use App\Support\StaffRecipients;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Matter lifecycle (spec §8):
 *   Intake → Conflict review → Engagement approval → Active ⇄ Pending / Stayed → Closed → Archived
 *
 * Every transition is authorised, validated against business rules, requires
 * a reason where it matters, and is written to matter_status_history.
 */
class MatterLifecycle
{
    /** Allowed transitions: from => [to, ...]. */
    public const TRANSITIONS = [
        'intake' => ['conflict_review', 'closed'],
        'conflict_review' => ['awaiting_engagement', 'intake', 'closed'],
        'awaiting_engagement' => ['active', 'conflict_review', 'closed'],
        'active' => ['pending', 'stayed', 'closed'],
        'pending' => ['active', 'stayed', 'closed'],
        'stayed' => ['active', 'pending', 'closed'],
        'closed' => ['archived', 'active'],
        'archived' => [],
    ];

    /** Transitions that must be justified in writing. */
    private const REASON_REQUIRED = ['stayed', 'pending', 'closed', 'intake', 'conflict_review'];

    /** @return array<string, string> statuses the matter may move to next, with labels */
    public static function nextStatuses(Matter $matter): array
    {
        return collect(self::TRANSITIONS[$matter->status] ?? [])
            ->mapWithKeys(fn ($s) => [$s => Matter::STATUSES[$s]])->all();
    }

    public static function reasonRequired(string $to): bool
    {
        return in_array($to, self::REASON_REQUIRED, true);
    }

    /** Create a matter in Intake, with its opening history entry and the lead on the team. */
    public function open(array $attributes, User $by): Matter
    {
        if (! Gate::forUser($by)->allows('create', Matter::class)) {
            throw new AuthorizationException('You may not open matters.');
        }

        return DB::transaction(function () use ($attributes, $by) {
            $matter = new Matter($attributes);
            $matter->status = 'intake';
            $matter->lead_user_id ??= $by->id;
            $matter->created_by = $by->id;
            $matter->save();

            $matter->assignments()->firstOrCreate(['user_id' => $matter->lead_user_id], ['role' => 'lead', 'assigned_by' => $by->id]);
            if ($matter->lead_user_id !== $by->id) {
                // The person opening the matter keeps access to finish intake.
                $matter->assignments()->firstOrCreate(['user_id' => $by->id], ['role' => 'support', 'assigned_by' => $by->id]);
            }

            $this->recordHistory($matter, null, 'intake', 'Matter opened', $by);

            return $matter;
        });
    }

    public function transition(Matter $matter, string $to, User $by, ?string $reason = null): Matter
    {
        if (! Gate::forUser($by)->allows('changeStatus', $matter)) {
            throw new AuthorizationException('You may not change the status of this matter.');
        }

        $from = $matter->status;
        $reason = filled($reason) ? trim($reason) : null;

        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "A matter cannot move from \"{$matter->statusLabel()}\" to \"".(Matter::STATUSES[$to] ?? $to).'".']);
        }
        if (self::reasonRequired($to) && $reason === null) {
            throw ValidationException::withMessages(['reason' => 'Please give a reason for this change.']);
        }

        $this->guard($matter, $to);

        return DB::transaction(function () use ($matter, $from, $to, $by, $reason) {
            $matter->status = $to;
            if ($to === 'active') {
                $matter->opened_at ??= now();
                $matter->closed_at = null;
                $matter->closure_reason = null;
            }
            if ($to === 'closed') {
                $matter->closed_at = now();
                $matter->closure_reason = $reason;
            }
            $matter->save();

            $this->recordHistory($matter, $from, $to, $reason, $by);

            if ($to === 'active' && $matter->client && $matter->client->status === 'prospect') {
                $matter->client->update(['status' => 'active']);
            }

            return $matter;
        });
    }

    /** Business rules that protect the firm (spec §7–8). */
    private function guard(Matter $matter, string $to): void
    {
        if ($to === 'awaiting_engagement' && ! $matter->conflictChecks()->whereIn('status', ['cleared', 'waived'])->exists()) {
            throw ValidationException::withMessages(['status' => 'A cleared (or waived) conflict check is required before engagement.']);
        }
        if ($to === 'active' && $matter->status === 'awaiting_engagement' && ! $matter->engagements()->where('status', 'approved')->exists()) {
            throw ValidationException::withMessages(['status' => 'An approved engagement is required before the matter becomes active.']);
        }
        if ($to === 'archived' && ($open = $matter->tasks()->where('status', '!=', 'done')->count() + $matter->deadlines()->whereNull('completed_at')->count()) > 0) {
            throw ValidationException::withMessages(['status' => "Complete or cancel the {$open} open task(s)/deadline(s) before archiving."]);
        }
    }

    // ---------------------------------------------------------------- engagements

    public function submitEngagement(Engagement $engagement, User $by): void
    {
        if (! Gate::forUser($by)->allows('update', $engagement)) {
            throw new AuthorizationException;
        }
        $check = $engagement->matter->conflictChecks()->whereIn('status', ['cleared', 'waived'])->latest('reviewed_at')->first();
        if (! $check) {
            throw ValidationException::withMessages(['engagement' => 'The matter needs a cleared (or waived) conflict check before the engagement can be submitted.']);
        }

        $engagement->forceFill(['status' => 'pending_approval', 'submitted_by' => $by->id, 'conflict_check_id' => $check->id, 'decision_notes' => null])->save();
        ActivityLog::record('engagement.submitted', $engagement, "Engagement for {$engagement->matter->reference} submitted for approval");

        StaffRecipients::notify('engagements.approve', new \App\Notifications\Practice\EngagementAwaitingApproval($engagement));
    }

    public function decideEngagement(Engagement $engagement, User $by, bool $approve, ?string $notes = null): void
    {
        if (! Gate::forUser($by)->allows('approve', $engagement)) {
            throw new AuthorizationException('You may not decide on this engagement.');
        }
        if (! $approve && blank($notes)) {
            throw ValidationException::withMessages(['notes' => 'Please explain why the engagement is declined.']);
        }

        $engagement->forceFill([
            'status' => $approve ? 'approved' : 'declined',
            'approved_by' => $by->id,
            'approved_at' => now(),
            'decision_notes' => $notes,
        ])->save();

        ActivityLog::record($approve ? 'engagement.approved' : 'engagement.declined', $engagement,
            'Engagement for '.$engagement->matter->reference.($approve ? ' approved' : ' declined'));
    }

    private function recordHistory(Matter $matter, ?string $from, string $to, ?string $reason, User $by): void
    {
        $matter->statusHistory()->create(['from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'user_id' => $by->id]);
        ActivityLog::record('matter.status', $matter, "{$matter->reference}: ".($from ? Matter::STATUSES[$from].' → ' : '').Matter::STATUSES[$to], userId: $by->id);
    }
}
