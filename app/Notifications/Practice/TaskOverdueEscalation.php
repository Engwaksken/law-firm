<?php

namespace App\Notifications\Practice;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;

class TaskOverdueEscalation extends PracticeNotification
{
    public function __construct(public Task $task) {}

    protected function title(): string { return 'Overdue task'; }

    protected function lines(): array
    {
        return array_values(array_filter([
            'A task is overdue'.($this->task->assignee ? ' (assigned to '.$this->task->assignee->name.')' : '').'.',
            $this->task->matter ? 'Matter: '.$this->task->matter->reference : null,
            'Was due: '.$this->task->due_at->format('j M Y, H:i'),
        ]));
    }

    protected function url(): string { return TaskResource::getUrl('edit', ['record' => $this->task], panel: 'admin'); }

    protected function icon(): string { return 'heroicon-o-exclamation-triangle'; }
}
