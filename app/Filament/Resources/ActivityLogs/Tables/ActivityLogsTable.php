<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Models\ActivityLog;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('event')->badge()->searchable()
                    ->color(fn (string $state) => match (true) {
                        str_starts_with($state, 'auth.failed') => 'danger',
                        str_starts_with($state, 'auth.') => 'info',
                        str_starts_with($state, 'access.') => 'warning',
                        str_ends_with($state, 'deleted') => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('user.name')->label('By')->placeholder('System')->searchable(),
                TextColumn::make('description')->limit(60)->wrap(),
                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('category')
                    ->options([
                        'auth.' => 'Authentication',
                        'access.' => 'Roles & permissions',
                        'User.' => 'User accounts',
                        'SiteSetting.' => 'Settings',
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $prefix) => $q->where('event', 'like', $prefix.'%'))),
                SelectFilter::make('user')->relationship('user', 'name')->searchable()->preload(),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
