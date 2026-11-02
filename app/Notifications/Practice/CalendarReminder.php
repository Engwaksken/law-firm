<?php

namespace App\Notifications\Practice;

use App\Filament\Pages\LegalCalendar;
use App\Models\Appointment;
use App\Models\Deadline;
use App\Models\Hearing;
use Illuminate\Database\Eloquent\Model;

/** "Hearing in 3 days · MAT-2611-ABC123 · 14 Nov 2026, 09:00". Never client names or matter titles. */
class CalendarReminder extends PracticeNotification
{
    public function __construct(public Model $entry, public int $daysBefore) {}

    public function kind(): string
    {
        return match (true) {
            $this->entry instanceof Hearing => Hearing::TYPES[$this->entry->type] ?? 'Hearing',
            $this->entry instanceof Deadline => 'Deadline',
            $this->entry instanceof Appointment => Appointment::TYPES[$this->entry->type] ?? 'Appointment',
            default => 'Calendar entry',
        };
    }

    public function when()
    {
        return $this->entry instanceof Deadline ? $this->entry->due_at : $this->entry->starts_at;
    }

    protected function title(): string
    {
        $in = match ($this->daysBefore) {
            0 => 'today',
            1 => 'tomorrow',
            default => "in {$this->daysBefore} days",
        };

        return $this->kind().' '.$in;
    }

    protected function lines(): array
    {
        return array_values(array_filter([
            $this->entry->matter ? 'Matter: '.$this->entry->matter->reference : null,
            'When: '.$this->when()->format('j M Y, H:i'),
        ]));
    }

    protected function url(): string { return LegalCalendar::getUrl(panel: 'admin'); }

    protected function icon(): string { return 'heroicon-o-calendar-days'; }
}
