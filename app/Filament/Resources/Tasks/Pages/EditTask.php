<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Support\MatterOptions;
use App\Models\Task;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/** @property Task $record */
class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Moving a task to another matter needs contribute rights on the new matter.
        if (($data['matter_id'] ?? null) !== $record->matter_id) {
            MatterOptions::authorizeFor(Task::class, $data['matter_id'] ?? null);
        }

        $previousAssignee = $record->assigned_to;
        $record->update($data);

        if ($record->assigned_to !== $previousAssignee) {
            // A new assignee means a fresh escalation cycle.
            $record->forceFill(['escalated_at' => null])->saveQuietly();
            CreateTask::notifyAssignee($record);
        }

        return $record;
    }
}
