<?php

namespace App\Filament\Resources\Pages\RelationManagers;

use App\Models\Page;
use App\Models\PageRevision;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Read-only history of published versions, with restore into the working copy. */
class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Revision history';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('reason')->badge()->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => $state === 'published' ? 'success' : 'gray'),
                TextColumn::make('user.name')->label('By')->placeholder('System'),
                TextColumn::make('blocks')->label('Blocks')
                    ->state(fn (PageRevision $record) => count($record->snapshot['blocks'] ?? [])),
                TextColumn::make('title')->label('Title at the time')
                    ->state(fn (PageRevision $record) => $record->snapshot['page']['title'] ?? '—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('restore')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->modalDescription('Replaces the current working copy with this version. The live page doesn\'t change until it is published again.')
                    ->visible(fn () => auth()->user()->can('update', $this->getOwnerRecord()))
                    ->action(function (PageRevision $record) {
                        /** @var Page $page */
                        $page = $this->getOwnerRecord();
                        $page->restoreRevision($record);
                        Notification::make()->title('Revision restored to the working copy')->success()->send();
                        $this->redirect(\App\Filament\Resources\Pages\PageResource::getUrl('edit', ['record' => $page]));
                    }),
            ]);
    }
}
