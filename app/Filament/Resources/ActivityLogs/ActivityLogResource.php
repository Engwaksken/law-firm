<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Models\ActivityLog;
use BackedEnum;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Read-only audit trail. No create/edit/delete pages exist, and the policy denies writes. */
class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?string $modelLabel = 'audit entry';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextEntry::make('created_at')->label('When')->dateTime(),
                TextEntry::make('event')->badge(),
                TextEntry::make('user.name')->label('By')->placeholder('System'),
                TextEntry::make('description'),
                TextEntry::make('subject_type')->label('Record type')->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : null)->placeholder('—'),
                TextEntry::make('subject_id')->label('Record ID')->placeholder('—'),
                TextEntry::make('ip_address')->label('IP address')->placeholder('—'),
                TextEntry::make('user_agent')->placeholder('—'),
                CodeEntry::make('properties')->label('Details')->columnSpanFull()
                    ->state(fn (ActivityLog $record) => $record->properties ? json_encode($record->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null)
                    ->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
