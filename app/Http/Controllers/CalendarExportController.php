<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Deadline;
use App\Models\Hearing;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * iCalendar feed of the signed-in user's visible calendar for the next 6 months.
 * Calendar apps sync to third-party servers, so each event's SUMMARY contains
 * only the entry type and matter reference: no client names, matter titles or notes.
 */
class CalendarExportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $from = now()->startOfDay();
        $to = now()->addMonths(6);

        $events = collect()
            ->merge(Hearing::query()->visibleTo($user)->whereBetween('starts_at', [$from, $to])->where('status', '!=', 'cancelled')->with('matter')->get()
                ->map(fn (Hearing $h) => $this->event('hearing-'.$h->id, (Hearing::TYPES[$h->type] ?? 'Hearing'), $h->matter?->reference, $h->starts_at, $h->ends_at ?? $h->starts_at->copy()->addHour())))
            ->merge(Deadline::query()->visibleTo($user)->whereNull('completed_at')->whereBetween('due_at', [$from, $to])->with('matter')->get()
                ->map(fn (Deadline $d) => $this->event('deadline-'.$d->id, 'Deadline', $d->matter?->reference, $d->due_at, $d->due_at->copy()->addMinutes(30))))
            ->merge(Appointment::query()->visibleTo($user)->where('status', 'scheduled')->whereBetween('starts_at', [$from, $to])->with('matter')->get()
                ->map(fn (Appointment $a) => $this->event('appointment-'.$a->id, (Appointment::TYPES[$a->type] ?? 'Appointment'), $a->matter?->reference, $a->starts_at, $a->ends_at ?? $a->starts_at->copy()->addHour())));

        $lines = array_merge([
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Law Firm Management System//Calendar//EN', 'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH', 'X-WR-CALNAME:'.$this->escape('My legal calendar'),
        ], $events->flatten()->all(), ['END:VCALENDAR']);

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="legal-calendar.ics"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** @return list<string> */
    private function event(string $uid, string $type, ?string $reference, Carbon $start, Carbon $end): array
    {
        return [
            'BEGIN:VEVENT',
            'UID:'.$uid.'@'.parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$start->copy()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$end->copy()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($type.($reference ? ' · '.$reference : '')),
            'END:VEVENT',
        ];
    }

    private function escape(string $text): string
    {
        return addcslashes(str_replace(["\r", "\n"], ' ', $text), ',;\\');
    }
}
