<?php

namespace App\Filament\Resources\Testimonials\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TestimonialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('author_name')->searchable()->description(fn ($record) => $record->author_title),
                TextColumn::make('quote')->limit(80)->wrap(),
                IconColumn::make('consent_obtained')->label('Consent')->boolean(),
                IconColumn::make('is_approved')->label('Approved')->boolean(),
                TextColumn::make('approver.name')->label('Approved by')->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_approved')->label('Approved'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }
}
