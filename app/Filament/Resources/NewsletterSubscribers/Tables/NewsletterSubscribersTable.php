<?php

namespace App\Filament\Resources\NewsletterSubscribers\Tables;

use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NewsletterSubscribersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')->searchable()->sortable()->copyable(),
                TextColumn::make('name')->searchable()->placeholder('—'),
                TextColumn::make('confirmed_at')->label('Confirmed')->dateTime('j M Y')->placeholder('Awaiting confirmation')->sortable(),
                TextColumn::make('unsubscribed_at')->label('Unsubscribed')->dateTime('j M Y')->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label('Signed up')->since()->sortable(),
            ])
            ->filters([
                Filter::make('active')->label('Active subscribers only')
                    ->query(fn (Builder $query) => $query->active())
                    ->default(),
            ])
            ->recordActions([
                DeleteAction::make()->modalDescription('Permanently removes this subscriber and their email address.'),
            ]);
    }
}
