<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Models\Article;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->limit(60)
                    ->description(fn (Article $r) => $r->typeLabel()),
                TextColumn::make('state')->label('Status')->badge()
                    ->state(fn (Article $r) => match (true) {
                        $r->status !== 'published' => 'Draft',
                        $r->published_at?->isFuture() => 'Scheduled',
                        default => 'Published',
                    })
                    ->color(fn (string $state) => ['Draft' => 'gray', 'Scheduled' => 'warning', 'Published' => 'success'][$state]),
                TextColumn::make('published_at')->dateTime('j M Y H:i')->sortable()->placeholder('—'),
                TextColumn::make('categories.name')->badge()->limitList(2)->toggleable(),
                TextColumn::make('author.name')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_featured')->label('Featured')->boolean()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(Article::TYPES),
                SelectFilter::make('state')->label('Status')
                    ->options(['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published'])
                    ->query(fn (Builder $q, array $data) => match ($data['value'] ?? null) {
                        'draft' => $q->where('status', '!=', 'published'),
                        'scheduled' => $q->where('status', 'published')->where('published_at', '>', now()),
                        'published' => $q->published(),
                        default => $q,
                    }),
                SelectFilter::make('categories')->relationship('categories', 'name')->multiple()->preload(),
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
