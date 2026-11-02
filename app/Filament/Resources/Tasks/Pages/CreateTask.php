<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Support\MatterOptions;
use App\Models\Task;
use App\Notifications\Practice\TaskAssigned;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        MatterOptions::authorizeFor(Task::class, $data['matter_id'] ?? null);

        return tap(Task::create($data), fn (Task $task) => static::notifyAssignee($task));
    }

    public static function notifyAssignee(Task $task): void
    {
        if ($task->assigned_to && $task->assigned_to !== auth()->id()) {
            $task->assignee?->notify(new TaskAssigned($task));
        }
    }
}
