<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Filament\Resources\ConflictChecks\ConflictCheckResource;
use App\Models\Client;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->badge()->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable()
                    ->description(fn (Client $record) => Client::TYPES[$record->type] ?? null),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => Client::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'active' => 'success', 'prospect' => 'info', default => 'gray' }),
                TextColumn::make('responsibleUser.name')->label('Responsible')->toggleable(),
                TextColumn::make('phone')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Since')->date()->sortable()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(Client::STATUSES),
                SelectFilter::make('type')->options(Client::TYPES),
                SelectFilter::make('responsible_user_id')->label('Responsible')->relationship('responsibleUser', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('conflictCheck')->label('Conflict check')->icon(Heroicon::OutlinedShieldExclamation)->color('warning')
                    ->visible(fn () => auth()->user()->can('conflicts.run'))
                    ->url(fn (Client $record) => ConflictCheckResource::getUrl('create', ['client' => $record->id])),
            ]);
    }
}
