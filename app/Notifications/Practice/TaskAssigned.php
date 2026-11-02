<?php

namespace App\Notifications\Practice;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;

class TaskAssigned extends PracticeNotification
{
    public function __construct(public Task $task) {}

    protected function title(): string { return 'Task assigned to you'; }

    protected function lines(): array
    {
        // Titles of tasks on restricted matters stay inside the system.
        $restricted = (bool) $this->task->matter?->is_restricted;

        return array_values(array_filter([
            $restricted ? 'A task on a restricted matter was assigned to you.' : 'Task: '.$this->task->title,
            $this->task->matter ? 'Matter: '.$this->task->matter->reference : null,
            $this->task->due_at ? 'Due: '.$this->task->due_at->format('j M Y, H:i') : null,
        ]));
    }

    protected function url(): string { return TaskResource::getUrl('edit', ['record' => $this->task], panel: 'admin'); }

    protected function icon(): string { return 'heroicon-o-clipboard-document-check'; }
}
