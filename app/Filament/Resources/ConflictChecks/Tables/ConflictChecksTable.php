<?php

namespace App\Filament\Resources\ConflictChecks\Tables;

use App\Models\ConflictCheck;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConflictChecksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['client', 'requester', 'parties']))
            ->columns([
                TextColumn::make('reference')->badge()->searchable(),
                TextColumn::make('parties_list')->label('Names')
                    ->state(fn (ConflictCheck $record) => $record->parties->pluck('name')->implode(', '))->limit(60)->wrap(),
                TextColumn::make('hits')->label('Matches')->state(fn (ConflictCheck $record) => $record->hitCount())
                    ->badge()->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => ConflictCheck::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'cleared' => 'success', 'conflict_found' => 'danger', 'waived' => 'warning', default => 'info' }),
                TextColumn::make('requester.name')->label('Run by'),
                TextColumn::make('created_at')->label('Run')->since()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('status')->options(ConflictCheck::STATUSES)])
            ->recordActions([ViewAction::make()]);
    }
}
