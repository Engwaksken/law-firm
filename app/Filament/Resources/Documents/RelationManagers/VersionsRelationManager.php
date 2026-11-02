<?php

namespace App\Filament\Resources\Documents\RelationManagers;

use App\Filament\Resources\Documents\DocumentResource;
use App\Legal\DocumentService;
use App\Models\DocumentVersion;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'Versions';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version_number')->label('Version')->prefix('v')->sortable(),
                TextColumn::make('original_name')->label('File'),
                TextColumn::make('size_bytes')->label('Size')->formatStateUsing(fn (int $state) => Number::fileSize($state)),
                TextColumn::make('sha256')->label('SHA-256')->limit(12)->copyable()->tooltip(fn (DocumentVersion $record) => $record->sha256)->fontFamily('mono'),
                TextColumn::make('scan_status')->label('Scan')->badge()
                    ->formatStateUsing(fn (string $state) => DocumentVersion::SCAN_STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => DocumentResource::scanColor($state)),
                TextColumn::make('uploader.name')->label('By'),
                TextColumn::make('created_at')->label('Uploaded')->dateTime(),
                TextColumn::make('change_notes')->label('Notes')->limit(40)->placeholder('—'),
            ])
            ->defaultSort('version_number', 'desc')
            ->recordActions([
                Action::make('download')->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (DocumentVersion $record) => $record->isDownloadable() && auth()->user()->can('download', $this->getOwnerRecord()))
                    ->action(fn (DocumentVersion $record) => redirect()->away(app(DocumentService::class)->temporaryDownloadUrl($record, auth()->user()))),
            ]);
    }
}
