<?php

namespace App\Filament\Resources\Consultations\Schemas;

use App\Models\Consultation;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Staff edit only scheduling fields; the requester's details stay as submitted. */
class ConsultationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Handling')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('status')->options(Consultation::STATUSES)->required(),
                    Select::make('advocate_id')->label('Advocate')
                        ->relationship('advocate', 'name', fn ($query) => $query->where('is_active', true))->preload()->searchable(),
                    DateTimePicker::make('scheduled_at')->label('Scheduled for')->seconds(false),
                    Textarea::make('internal_notes')->rows(4)->columnSpanFull()
                        ->helperText('Staff only. Never shown to the requester.'),
                ]),
        ]);
    }
}
