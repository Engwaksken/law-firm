<?php

namespace App\Filament\Support;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;

/** Form pieces shared by hearings, deadlines and appointments. */
class CalendarFields
{
    public const REMINDER_OPTIONS = [14 => '14 days before', 7 => '7 days before', 3 => '3 days before', 1 => '1 day before', 0 => 'On the day'];

    public static function reminders(): CheckboxList
    {
        return CheckboxList::make('reminder_days')->label('Reminders')
            ->options(self::REMINDER_OPTIONS)->columns(5)
            ->default(fn () => array_map('intval', (array) config('firm.reminder_days')))
            ->helperText('Notification preferences only. They are not legal deadline calculations.')
            ->columnSpanFull();
    }

    /** Matter select limited to matters the user may add $modelClass entries to (current value kept on edit). */
    public static function matter(string $modelClass, bool $required = true): Select
    {
        return Select::make('matter_id')->label('Matter')->searchable()->required($required)->live()
            ->options(fn (?Model $record) => MatterOptions::contributable($modelClass)
                + ($record?->matter ? [$record->matter_id => $record->matter->label()] : []));
    }
}
