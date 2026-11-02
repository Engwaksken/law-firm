<?php

namespace App\Console\Commands;

use App\Legal\CalendarReminders;
use Illuminate\Console\Command;

class SendCalendarReminders extends Command
{
    protected $signature = 'practice:send-reminders';

    protected $description = 'Send hearing, deadline and appointment reminders (each reminder at most once).';

    public function handle(CalendarReminders $reminders): int
    {
        $count = $reminders->send();
        $this->info("Sent {$count} reminder(s).");

        return self::SUCCESS;
    }
}
