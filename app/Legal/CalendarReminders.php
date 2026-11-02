<?php

namespace App\Legal;

use App\Models\Appointment;
use App\Models\Deadline;
use App\Models\Hearing;
use App\Models\Matter;
use App\Models\User;
use App\Notifications\Practice\CalendarReminder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sends "X in N days" reminders according to each entry's reminder_days
 * (default config firm.reminder_days). Each (entry, user, offset, date) is
 * sent once, even if the command runs repeatedly. Reminders are notification
 * preferences, not legal deadline calculations.
 */
class CalendarReminders
{
    /** @return int number of notifications sent */
    public function send(?Carbon $today = null): int
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $defaults = array_map('intval', (array) config('firm.reminder_days', [14, 7, 3, 1, 0]));
        $horizon = $today->copy()->addDays(max(array_merge($defaults, [14])) + 1);
        $sent = 0;

        $entries = collect()
            ->merge(Hearing::query()->where('status', 'scheduled')->whereBetween('starts_at', [$today, $horizon])->with('matter.assignments')->get())
            ->merge(Deadline::query()->whereNull('completed_at')->whereBetween('due_at', [$today, $horizon])->with('matter')->get())
            ->merge(Appointment::query()->where('status', 'scheduled')->whereBetween('starts_at', [$today, $horizon])->get());

        foreach ($entries as $entry) {
            $date = ($entry instanceof Deadline ? $entry->due_at : $entry->starts_at)->copy()->startOfDay();
            $daysBefore = (int) $today->diffInDays($date);
            $offsets = array_map('intval', $entry->reminder_days ?? $defaults);
            if (! in_array($daysBefore, $offsets, true)) {
                continue;
            }

            foreach ($this->recipients($entry) as $user) {
                $inserted = DB::table('calendar_reminders_sent')->insertOrIgnore([
                    'remindable_type' => $entry->getMorphClass(),
                    'remindable_id' => $entry->getKey(),
                    'user_id' => $user->id,
                    'days_before' => $daysBefore,
                    'for_date' => $date->toDateString(),
                    'sent_at' => now(),
                ]);
                if ($inserted) {
                    $user->notify(new CalendarReminder($entry, $daysBefore));
                    $sent++;
                }
            }
        }

        return $sent;
    }

    /** @return Collection<int, User> */
    public function recipients(Model $entry): Collection
    {
        $users = match (true) {
            $entry instanceof Hearing => $this->matterTeam($entry->matter),
            $entry instanceof Deadline => $entry->responsible_user_id
                ? User::query()->whereKey($entry->responsible_user_id)->get()
                : User::query()->whereKey($entry->matter?->lead_user_id)->get(),
            $entry instanceof Appointment => $entry->participants(),
            default => collect(),
        };

        return $users->filter(fn (User $u) => $u->is_active)->unique('id')->values();
    }

    private function matterTeam(?Matter $matter): Collection
    {
        if (! $matter) {
            return collect();
        }

        return User::query()->whereIn('id', array_filter(array_merge(
            [$matter->lead_user_id],
            $matter->assignments->pluck('user_id')->all(),
        )))->get();
    }
}
