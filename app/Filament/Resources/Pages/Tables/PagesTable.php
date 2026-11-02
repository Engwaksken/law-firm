<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Models\Page;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable()
                    ->description(fn (Page $record) => '/'.$record->slug.((int) Settings::get('homepage_page_id') === $record->id ? '  (homepage)' : '')),
                TextColumn::make('status')->badge()
                    ->state(fn (Page $record) => $record->displayStatus())
                    ->color(fn (string $state) => match (true) {
                        str_starts_with($state, 'Published ·') => 'warning',
                        $state === 'Published' => 'success',
                        $state === 'Scheduled', $state === 'In review' => 'info',
                        $state === 'Archived' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('published_at')->label('Published')->dateTime()->sortable()->placeholder('Never'),
                TextColumn::make('updated_at')->label('Last edited')->since()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(Page::STATUSES),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('view')->label('View')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->url(fn (Page $record) => $record->url(), shouldOpenInNewTab: true)
                    ->visible(fn (Page $record) => $record->isPublished()),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
