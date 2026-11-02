<?php

namespace App\Filament\Resources\Matters\RelationManagers;

use App\Models\Matter;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Read-only, append-only status history (spec §8: user, timestamp, reason). */
class StatusHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistory';

    protected static ?string $title = 'Status history';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime(),
                TextColumn::make('from_status')->label('From')->formatStateUsing(fn (?string $state) => $state ? Matter::STATUSES[$state] ?? $state : '—')->placeholder('—'),
                TextColumn::make('to_status')->label('To')->badge()->formatStateUsing(fn (string $state) => Matter::STATUSES[$state] ?? $state),
                TextColumn::make('user.name')->label('By')->placeholder('System'),
                TextColumn::make('reason')->wrap()->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
