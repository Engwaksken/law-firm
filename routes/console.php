<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Legal practice. Requires the scheduler: `* * * * * php artisan schedule:run`.
Schedule::command('practice:send-reminders')->dailyAt('06:30')->withoutOverlapping();
Schedule::command('practice:escalate-overdue-tasks')->hourly()->withoutOverlapping();
