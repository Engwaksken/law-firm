<?php

namespace App\Filament\Resources\Matters\Tables;

use App\Models\Matter;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MattersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->badge()->searchable()->sortable(),
                TextColumn::make('title')->searchable()->sortable()->wrap()
                    ->description(fn (Matter $record) => $record->client?->name),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => Matter::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'active' => 'success', 'stayed', 'pending' => 'warning', 'closed', 'archived' => 'gray', default => 'info' }),
                TextColumn::make('priority')->badge()->formatStateUsing(fn (string $state) => Matter::PRIORITIES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'urgent' => 'danger', 'high' => 'warning', default => 'gray' })->toggleable(),
                TextColumn::make('lead.name')->label('Lead')->toggleable(),
                TextColumn::make('practiceArea.name')->label('Practice area')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('case_number')->searchable()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_restricted')->label('Restricted')->boolean()->trueIcon('heroicon-o-lock-closed')->falseIcon('')->toggleable(),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Filter::make('mine')->label('My matters')->default()
                    ->query(fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('lead_user_id', auth()->id())
                        ->orWhereHas('assignments', fn (Builder $a) => $a->where('user_id', auth()->id())))),
                SelectFilter::make('status')->options(Matter::STATUSES)->multiple()
                    ->default(['intake', 'conflict_review', 'awaiting_engagement', 'active', 'pending', 'stayed']),
                SelectFilter::make('priority')->options(Matter::PRIORITIES),
                SelectFilter::make('practice_area_id')->label('Practice area')->relationship('practiceArea', 'name'),
                SelectFilter::make('lead_user_id')->label('Lead')->relationship('lead', 'name'),
                TernaryFilter::make('is_restricted')->label('Restricted'),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }
}
