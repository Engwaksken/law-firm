<?php

namespace App\Filament\Resources\Departments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Department details')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('code')->required()->maxLength(20)->unique(ignoreRecord: true),
                    Select::make('office_id')->relationship('office', 'name')->searchable()->preload()
                        ->helperText('Leave empty for a firm-wide department.'),
                    Toggle::make('is_active')->label('Active')->default(true)->inline(false),
                    Textarea::make('description')->rows(3)->columnSpanFull(),
                ]),
        ]);
    }
}
