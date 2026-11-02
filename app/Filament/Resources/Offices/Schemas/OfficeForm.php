<?php

namespace App\Filament\Resources\Offices\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OfficeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Office details')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->required()->maxLength(255)->unique(ignoreRecord: true),
                    TextInput::make('code')->required()->maxLength(20)->unique(ignoreRecord: true)
                        ->helperText('Short code used in references, e.g. HQ, KLA, EBB.'),
                    TextInput::make('email')->email()->maxLength(255),
                    TextInput::make('phone')->tel()->maxLength(50),
                    TextInput::make('address')->maxLength(255)->columnSpanFull(),
                    TextInput::make('city')->maxLength(100),
                    TextInput::make('country')->default('Uganda')->required()->maxLength(100),
                    Toggle::make('is_head_office')->label('Head office'),
                    Toggle::make('is_active')->label('Active')->default(true),
                ]),
        ]);
    }
}
