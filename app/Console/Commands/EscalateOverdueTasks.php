<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Notifications\Practice\TaskOverdueEscalation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class EscalateOverdueTasks extends Command
{
    protected $signature = 'practice:escalate-overdue-tasks';

    protected $description = 'Notify the assignee and the matter lead (or task creator) once when a task becomes overdue.';

    public function handle(): int
    {
        $count = 0;

        Task::query()->overdue()->whereNull('escalated_at')->with('matter', 'assignee')->chunkById(100, function ($tasks) use (&$count) {
            foreach ($tasks as $task) {
                $recipients = User::query()->where('is_active', true)
                    ->whereIn('id', array_filter([$task->assigned_to, $task->matter?->lead_user_id ?? $task->created_by]))
                    ->get();

                // Mark first so a notification failure can't cause repeated escalation emails.
                $task->forceFill(['escalated_at' => now()])->saveQuietly();
                Notification::send($recipients, new TaskOverdueEscalation($task));
                $count++;
            }
        });

        $this->info("Escalated {$count} task(s).");

        return self::SUCCESS;
    }
}
