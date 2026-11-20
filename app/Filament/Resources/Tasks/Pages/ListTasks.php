<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->using(function (array $data) {
            \App\Filament\Support\MatterOptions::authorizeFor(\App\Models\Task::class, $data['matter_id'] ?? null);

            $task = \App\Models\Task::create($data);
            CreateTask::notifyAssignee($task);

            return $task;
        })];
    }
}
