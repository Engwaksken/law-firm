<?php

namespace App\Filament\Resources\Media\Tables;

use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('uploader'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('thumbnail')->label('')
                    ->state(fn (Media $record) => $record->isImage() ? $record->path : null)
                    ->disk(MediaResource::DISK)->imageHeight(56)->square()
                    ->defaultImageUrl(fn () => 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="1.5"><path d="M19.5 14.25v-2.6a3.4 3.4 0 0 0-3.4-3.4h-1.5A1.1 1.1 0 0 1 13.5 7.1V5.6a3.4 3.4 0 0 0-3.4-3.4H8.25m2.25 0H5.6c-.6 0-1.1.5-1.1 1.1v17.4c0 .6.5 1.1 1.1 1.1h12.8c.6 0 1.1-.5 1.1-1.1V11.25a9 9 0 0 0-9-9Z"/></svg>')),
                TextColumn::make('title')->label('Name')
                    ->state(fn (Media $record) => $record->title ?: $record->original_name)
                    ->description(fn (Media $record) => $record->alt_text)
                    ->searchable(['title', 'original_name', 'alt_text'])->wrap(),
                TextColumn::make('mime_type')->label('Type')->badge()->color('gray')
                    ->formatStateUsing(fn (?string $state) => $state ? strtoupper(str($state)->afterLast('/')->replace(['svg+xml', 'vnd.openxmlformats-officedocument.wordprocessingml.document'], ['svg', 'docx'])) : '—'),
                TextColumn::make('size_bytes')->label('Size')->formatStateUsing(fn (?int $state) => MediaResource::humanSize($state))->sortable(),
                TextColumn::make('dimensions')->state(fn (Media $record) => $record->width ? "{$record->width}×{$record->height}" : null)->placeholder('—')->toggleable(),
                TextColumn::make('collection')->formatStateUsing(fn (string $state) => MediaResource::COLLECTIONS[$state] ?? $state)->toggleable(),
                TextColumn::make('url')->label('URL')->state(fn (Media $record) => $record->url())
                    ->copyable()->copyMessage('URL copied')->limit(30)->toggleable(),
                TextColumn::make('uploader.name')->label('Uploaded by')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Uploaded')->date('j M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('collection')->options(MediaResource::COLLECTIONS),
                SelectFilter::make('kind')->label('Type')
                    ->options(['image' => 'Images', 'document' => 'Documents'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'image' => $query->where('mime_type', 'like', 'image/%'),
                        'document' => $query->where(fn (Builder $query) => $query->where('mime_type', 'not like', 'image/%')->orWhereNull('mime_type')),
                        default => $query,
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->modalDescription('This permanently deletes the file. Pages or events using it will lose the image.')
                    ->before(function (Media $record, ForceDeleteAction $action) {
                        if (($count = MediaResource::referenceCount($record)) > 0) {
                            Notification::make()->danger()->title('File is in use')
                                ->body("It is used by {$count} page(s) or event(s). Replace it there first.")->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->toolbarActions([
                // No bulk force delete: every permanent deletion goes through the in-use check above.
                BulkActionGroup::make([DeleteBulkAction::make(), RestoreBulkAction::make()]),
            ]);
    }
}
