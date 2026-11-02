<?php

namespace App\Filament\Resources\ConflictChecks;

use App\Filament\Resources\ConflictChecks\Pages\CreateConflictCheck;
use App\Filament\Resources\ConflictChecks\Pages\ListConflictChecks;
use App\Filament\Resources\ConflictChecks\Pages\ViewConflictCheck;
use App\Filament\Resources\ConflictChecks\Schemas\ConflictCheckForm;
use App\Filament\Resources\ConflictChecks\Schemas\ConflictCheckInfolist;
use App\Filament\Resources\ConflictChecks\Tables\ConflictChecksTable;
use App\Models\ConflictCheck;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Conflict checks are immutable once run; only the review decision is added. */
class ConflictCheckResource extends Resource
{
    protected static ?string $model = ConflictCheck::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|UnitEnum|null $navigationGroup = 'Legal Practice';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Schema $schema): Schema { return ConflictCheckForm::configure($schema); }

    public static function infolist(Schema $schema): Schema { return ConflictCheckInfolist::configure($schema); }

    public static function table(Table $table): Table { return ConflictChecksTable::configure($table); }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->can('conflicts.review')) {
            return null;
        }
        $count = ConflictCheck::where('status', 'pending_review')->where('requested_by', '!=', auth()->id())->count();

        return $count ? (string) $count : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConflictChecks::route('/'),
            'create' => CreateConflictCheck::route('/create'),
            'view' => ViewConflictCheck::route('/{record}'),
        ];
    }
}
