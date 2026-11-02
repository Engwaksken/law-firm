<?php

namespace App\Filament\Resources\Matters\Pages;

use App\Filament\Resources\Matters\MatterResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMatter extends EditRecord
{
    protected static string $resource = MatterResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    /** Status, client and (for non-managers) the ethical-wall flag can't be changed here. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        unset($data['status'], $data['client_id']);
        if (! auth()->user()->can('matters.assign')) {
            unset($data['is_restricted']);
        }

        $record->update($data);

        // The lead must always be on the team.
        if ($record->lead_user_id) {
            $record->assignments()->updateOrCreate(['user_id' => $record->lead_user_id], ['role' => 'lead', 'assigned_by' => auth()->id()]);
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return MatterResource::getUrl('view', ['record' => $this->record]);
    }
}
