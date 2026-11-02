<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->label('Role')
                    ->description(fn (Role $record) => $record->description)
                    ->searchable(query: fn ($query, string $search) => $query->where('name', 'like', "%{$search}%")),
                TextColumn::make('name')->label('Key')->badge()->color('gray'),
                TextColumn::make('permissions_count')->counts('permissions')->label('Permissions')->sortable(),
                TextColumn::make('users_count')->counts('users')->label('Users')->sortable(),
                IconColumn::make('system')->label('Built-in')->boolean()
                    ->state(fn (Role $record) => $record->isSystemRole()),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
