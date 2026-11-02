<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\Deadline;
use App\Models\Hearing;
use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Personal practice snapshot. Every count is limited to what the user may see. */
class MyPracticeOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'My practice';

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) $user && ($user->can('matters.view-assigned') || $user->can('matters.view-all'));
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $mine = fn ($query) => $query->whereIn('matter_id', \App\Models\Matter::query()
            ->where('lead_user_id', $user->id)
            ->orWhereHas('assignments', fn ($query) => $query->where('user_id', $user->id))
            ->select('id'));

        $todayHearings = Hearing::query()->visibleTo($user)->where($mine)->where('status', 'scheduled')
            ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])->count();
        $todayAppointments = Appointment::query()->visibleTo($user)->where('status', 'scheduled')
            ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
            ->where(fn ($query) => $query->where('organizer_id', $user->id)->orWhereJsonContains('attendee_ids', $user->id)->orWhereJsonContains('attendee_ids', (string) $user->id))
            ->count();
        $upcomingHearings = Hearing::query()->visibleTo($user)->where($mine)->where('status', 'scheduled')
            ->whereBetween('starts_at', [now(), now()->addDays(7)])->count();
        $overdueTasks = Task::query()->visibleTo($user)->where('assigned_to', $user->id)->overdue()->count();
        $deadlinesThisWeek = Deadline::query()->visibleTo($user)->whereNull('completed_at')
            ->whereBetween('due_at', [now()->startOfDay(), now()->endOfWeek()])
            ->where(fn ($query) => $query->where('responsible_user_id', $user->id)
                ->orWhere(fn ($query) => $query->whereNull('responsible_user_id')->whereIn('matter_id', \App\Models\Matter::query()->where('lead_user_id', $user->id)->select('id'))))
            ->count();

        return [
            Stat::make('Today', $todayHearings + $todayAppointments)
                ->description("{$todayHearings} hearing(s), {$todayAppointments} appointment(s)")->icon('heroicon-o-sun'),
            Stat::make('Hearings in the next 7 days', $upcomingHearings)->icon('heroicon-o-building-library'),
            Stat::make('Deadlines this week', $deadlinesThisWeek)->icon('heroicon-o-clock')->color($deadlinesThisWeek ? 'warning' : 'gray'),
            Stat::make('My overdue tasks', $overdueTasks)->icon('heroicon-o-exclamation-triangle')->color($overdueTasks ? 'danger' : 'success'),
        ];
    }
}
