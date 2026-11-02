<?php

namespace App\Filament\Resources\ConflictChecks\Pages;

use App\Filament\Resources\ConflictChecks\ConflictCheckResource;
use App\Legal\ConflictSearch;
use App\Models\Client;
use App\Models\Matter;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class CreateConflictCheck extends CreateRecord
{
    protected static string $resource = ConflictCheckResource::class;

    protected static ?string $title = 'Run conflict check';

    /** Prefill from ?client= and ?matter= (client name, contacts and known parties). */
    protected function fillForm(): void
    {
        $parties = [];
        $client = request()->integer('client') ? Client::find(request()->integer('client')) : null;
        $matter = request()->integer('matter') ? Matter::find(request()->integer('matter')) : null;
        if ($matter && ! Gate::allows('view', $matter)) {
            $matter = null;
        }
        $client ??= $matter?->client;

        if ($client) {
            $parties[] = ['name' => $client->name, 'role' => 'prospective_client'];
            foreach ($client->contacts as $contact) {
                $parties[] = ['name' => $contact->name, 'role' => 'related'];
            }
        }
        foreach ($matter?->parties ?? [] as $party) {
            $parties[] = ['name' => $party->name, 'role' => in_array($party->party_type, ['opposing', 'opposing_counsel'], true) ? 'opposing' : 'related'];
        }

        $this->form->fill([
            'client_id' => $client?->id,
            'matter_id' => $matter?->id,
            'parties' => $parties ?: [['name' => '', 'role' => 'prospective_client']],
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $matter = filled($data['matter_id'] ?? null) ? Matter::find($data['matter_id']) : null;
        abort_if($matter && ! Gate::allows('view', $matter), 403);

        return app(ConflictSearch::class)->run(
            $data['parties'] ?? [],
            auth()->user(),
            filled($data['client_id'] ?? null) ? Client::find($data['client_id']) : null,
            $matter,
        );
    }

    protected function getRedirectUrl(): string
    {
        return ConflictCheckResource::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Conflict check run: '.$this->record->hitCount().' potential match(es) for review';
    }
}
