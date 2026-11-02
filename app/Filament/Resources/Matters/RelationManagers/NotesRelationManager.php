<?php

namespace App\Filament\Resources\Matters\RelationManagers;

use App\Models\CaseNote;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/** Internal case notes: encrypted, privileged by default, never shown to clients. */
class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Case notes';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->maxLength(160)->columnSpanFull(),
            Textarea::make('body')->label('Note')->required()->rows(8)->columnSpanFull(),
            Toggle::make('is_privileged')->label('Privileged (advocate–client privilege)')->default(true),
        ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title')->placeholder('Untitled'),
            TextEntry::make('author.name')->label('Author'),
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('body')->columnSpanFull()->prose()->formatStateUsing(fn ($state) => nl2br(e($state)))->html(),
        ]);
    }

    public function table(Table $table): Table
    {
        $matter = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('author'))
            ->columns([
                TextColumn::make('title')->placeholder('Untitled')
                    ->description(fn (CaseNote $record) => str($record->body)->limit(90)->toString()),
                TextColumn::make('author.name')->label('Author'),
                IconColumn::make('is_privileged')->label('Privileged')->boolean(),
                TextColumn::make('created_at')->label('Written')->since()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()->label('Add note')
                    ->visible(fn () => Gate::allows('createFor', [CaseNote::class, $matter]))
                    ->authorize(fn () => Gate::allows('createFor', [CaseNote::class, $matter])),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (CaseNote $record) => auth()->user()->can('update', $record))
                    ->authorize(fn (CaseNote $record) => auth()->user()->can('update', $record)),
                DeleteAction::make()->visible(fn (CaseNote $record) => auth()->user()->can('delete', $record))
                    ->authorize(fn (CaseNote $record) => auth()->user()->can('delete', $record)),
            ]);
    }
}
