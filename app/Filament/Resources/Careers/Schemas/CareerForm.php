<?php

namespace App\Filament\Resources\Careers\Schemas;

use App\Models\Career;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CareerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Vacancy')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('title')->required()->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, ?string $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')->required()->maxLength(255)->alphaDash()->unique(ignoreRecord: true),
                    Select::make('type')->options(Career::TYPES)->default('job')->required(),
                    TextInput::make('employment_type')->maxLength(100)->placeholder('Full-time, part-time, contract…'),
                    Select::make('office_id')->label('Office')->relationship('office', 'name')->preload(),
                    Select::make('department_id')->label('Department')->relationship('department', 'name')->preload(),
                    TextInput::make('location')->maxLength(255),
                    TextInput::make('summary')->maxLength(300),
                    RichEditor::make('description')->required()->columnSpanFull(),
                    RichEditor::make('requirements')->columnSpanFull(),
                ]),
            Section::make('Publishing')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('status')->options(Career::STATUSES)->default('draft')->required()->live(),
                    DateTimePicker::make('published_at')->label('Publish at')->seconds(false)
                        ->required(fn (Get $get) => $get('status') === 'published')
                        ->default(now()),
                    DatePicker::make('closes_at')->label('Closing date')
                        ->helperText('Applications close at the end of this day.'),
                ]),
        ]);
    }
}
