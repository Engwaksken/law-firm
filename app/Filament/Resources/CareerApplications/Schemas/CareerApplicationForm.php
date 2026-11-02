<?php

namespace App\Filament\Resources\CareerApplications\Schemas;

use App\Models\CareerApplication;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Staff change only the status and their notes; applicant data stays as submitted. */
class CareerApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Review')
                ->columnSpanFull()
                ->schema([
                    Select::make('status')->options(CareerApplication::STATUSES)->required()
                        ->helperText('The applicant is emailed when the status changes to: '.collect(CareerApplication::NOTIFY_ON)->map(fn ($s) => CareerApplication::STATUSES[$s])->implode(', ').'.'),
                    Textarea::make('internal_notes')->rows(5)->helperText('Staff only. Never sent to the applicant.'),
                ]),
        ]);
    }
}
