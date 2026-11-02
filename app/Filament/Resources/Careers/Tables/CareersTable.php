<?php

namespace App\Filament\Resources\Careers\Tables;

use App\Models\Career;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CareersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->description(fn (Career $r) => $r->location),
                TextColumn::make('type')->badge()->formatStateUsing(fn (string $state) => Career::TYPES[$state] ?? $state),
                TextColumn::make('state')->label('Status')->badge()
                    ->state(fn (Career $r) => match (true) {
                        $r->status === 'draft' => 'Draft',
                        $r->status === 'closed' => 'Closed',
                        $r->isOpen() => 'Open',
                        $r->published_at?->isFuture() => 'Scheduled',
                        default => 'Expired',
                    })
                    ->color(fn (string $state) => ['Open' => 'success', 'Scheduled' => 'warning', 'Draft' => 'gray', 'Closed' => 'gray', 'Expired' => 'danger'][$state]),
                TextColumn::make('closes_at')->label('Closes')->date('j M Y')->placeholder('—')->sortable(),
                TextColumn::make('applications_count')->counts('applications')->label('Applications')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(Career::TYPES),
                SelectFilter::make('status')->options(Career::STATUSES),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
