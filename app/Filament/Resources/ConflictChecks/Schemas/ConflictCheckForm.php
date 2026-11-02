<?php

namespace App\Filament\Resources\ConflictChecks\Schemas;

use App\Models\Client;
use App\Models\ConflictCheckParty;
use App\Models\Matter;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConflictCheckForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Related records (optional)')->columns(2)->columnSpanFull()->schema([
                Select::make('client_id')->label('Prospective / existing client')
                    ->options(fn () => Client::query()->orderBy('name')->limit(500)->get()->mapWithKeys(fn (Client $c) => [$c->id => "{$c->reference} · {$c->name}"]))
                    ->searchable(),
                Select::make('matter_id')->label('Matter')
                    ->options(fn () => Matter::query()->visibleTo(auth()->user())->whereIn('status', ['intake', 'conflict_review'])->get()->mapWithKeys(fn (Matter $m) => [$m->id => $m->label()]))
                    ->searchable()
                    ->helperText('A matter in Intake moves to Conflict review automatically.'),
            ]),
            Section::make('Names to search')
                ->description('Include the prospective client, opposing parties, their counsel, and related persons or companies. The search covers all clients, contacts and matter parties, including restricted matters.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('parties')->hiddenLabel()
                        ->schema([
                            TextInput::make('name')->required()->maxLength(255),
                            Select::make('role')->options(ConflictCheckParty::ROLES)->default('opposing')->required(),
                        ])
                        ->columns(2)->minItems(1)->defaultItems(1)->addActionLabel('Add name'),
                ]),
        ]);
    }
}
