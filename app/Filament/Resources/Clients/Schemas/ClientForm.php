<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Models\Client;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Client')->columns(2)->columnSpanFull()->schema([
                Select::make('type')->options(Client::TYPES)->default('individual')->required()->live(),
                Select::make('status')->options(Client::STATUSES)->default('prospect')->required(),
                TextInput::make('name')->label(fn (Get $get) => $get('type') === 'corporate' ? 'Registered name' : 'Full legal name')->required()->maxLength(255),
                TextInput::make('registration_number')->visible(fn (Get $get) => $get('type') === 'corporate')->maxLength(100),
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('phone')->tel()->maxLength(40),
                TextInput::make('address')->maxLength(255),
                TextInput::make('city')->maxLength(100),
                TextInput::make('country')->default('Uganda')->maxLength(100),
                TextInput::make('category')->maxLength(100)->datalist(['Individual', 'SME', 'Corporate', 'NGO', 'Government', 'Financial institution']),
                Select::make('responsible_user_id')->label('Responsible advocate')
                    ->options(fn () => User::query()->where('is_active', true)->permission('matters.view-assigned')->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                Select::make('office_id')->relationship('office', 'name'),
                TextInput::make('intake_source')->maxLength(100)->datalist(['Website', 'Referral', 'Walk-in', 'Existing client', 'Other']),
            ]),

            Section::make('Identification')
                ->description('Collect only when legitimately necessary (e.g. engagement, statutory due diligence). Stored encrypted.')
                ->columns(2)->columnSpanFull()->collapsible()
                ->visible(fn (?Client $record) => $record === null || auth()->user()->can('viewIdentification', $record))
                ->schema([
                    Select::make('id_type')->options(Client::ID_TYPES),
                    TextInput::make('id_number')->label('ID number')->maxLength(60)->password()->revealable(),
                ]),

            Section::make('Contacts & authorised representatives')->columnSpanFull()->collapsible()->schema([
                Repeater::make('contacts')->relationship()->hiddenLabel()
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('position')->maxLength(120),
                        TextInput::make('email')->email()->maxLength(255),
                        TextInput::make('phone')->tel()->maxLength(40),
                        Toggle::make('is_authorized_representative')->label('Authorised representative'),
                        Toggle::make('is_primary')->label('Primary contact'),
                    ])->columns(3)->defaultItems(0)->addActionLabel('Add contact')
                    ->itemLabel(fn (array $state) => $state['name'] ?? null),
            ]),

            Section::make('Confidential notes')->columnSpanFull()->collapsible()->collapsed()->schema([
                Textarea::make('notes')->hiddenLabel()->rows(4)->helperText('Encrypted. Visible to staff with client access.'),
            ]),
        ]);
    }
}
