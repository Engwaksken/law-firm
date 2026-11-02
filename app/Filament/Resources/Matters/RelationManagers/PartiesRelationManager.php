<?php

namespace App\Filament\Resources\Matters\RelationManagers;

use App\Models\MatterParty;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Opposing and related parties. These names feed future conflict searches. */
class PartiesRelationManager extends RelationManager
{
    protected static string $relationship = 'parties';

    protected static ?string $title = 'Parties';

    private function userCanEditParties(): bool
    {
        return auth()->user()->can('update', $this->getOwnerRecord());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            Select::make('party_type')->label('Role')->options(MatterParty::TYPES)->default('opposing')->required(),
            Toggle::make('is_organization')->label('Organisation'),
            TextInput::make('contact')->maxLength(255),
            Textarea::make('notes')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('party_type')->label('Role')->badge()->formatStateUsing(fn (string $state) => MatterParty::TYPES[$state] ?? $state),
                IconColumn::make('is_organization')->label('Org.')->boolean(),
                TextColumn::make('contact')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label('Add party')->visible(fn () => $this->userCanEditParties())->authorize(fn () => $this->userCanEditParties()),
            ])
            ->recordActions([
                EditAction::make()->visible(fn () => $this->userCanEditParties())->authorize(fn () => $this->userCanEditParties()),
                DeleteAction::make()->visible(fn () => $this->userCanEditParties())->authorize(fn () => $this->userCanEditParties()),
            ]);
    }
}
