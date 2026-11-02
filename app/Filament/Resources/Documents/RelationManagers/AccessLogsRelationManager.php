<?php

namespace App\Filament\Resources\Documents\RelationManagers;

use App\Models\Document;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Chain of custody: who uploaded, viewed, downloaded, approved or deleted. */
class AccessLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'accessLogs';

    protected static ?string $title = 'Access log';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Document $ownerRecord */
        $user = auth()->user();

        return $ownerRecord->canBeSeenBy($user)
            && ($user->can('documents.approve') || $ownerRecord->matter?->lead_user_id === $user->id);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('action')->badge()->color(fn (string $state) => match ($state) {
                    'download', 'view' => 'info', 'delete' => 'danger', 'approve' => 'success', default => 'gray',
                }),
                TextColumn::make('version.version_number')->label('Version')->prefix('v')->placeholder('—'),
                TextColumn::make('user.name')->label('User')->placeholder('System'),
                TextColumn::make('ip_address')->label('IP')->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
