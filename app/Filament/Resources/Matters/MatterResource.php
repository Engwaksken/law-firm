<?php

namespace App\Filament\Resources\Matters;

use App\Filament\Resources\Matters\Pages\CreateMatter;
use App\Filament\Resources\Matters\Pages\EditMatter;
use App\Filament\Resources\Matters\Pages\ListMatters;
use App\Filament\Resources\Matters\Pages\ViewMatter;
use App\Filament\Resources\Matters\RelationManagers;
use App\Filament\Resources\Matters\Schemas\MatterForm;
use App\Filament\Resources\Matters\Schemas\MatterInfolist;
use App\Filament\Resources\Matters\Tables\MattersTable;
use App\Models\Matter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MatterResource extends Resource
{
    protected static ?string $model = Matter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Legal Practice';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Schema $schema): Schema { return MatterForm::configure($schema); }

    public static function infolist(Schema $schema): Schema { return MatterInfolist::configure($schema); }

    public static function table(Table $table): Table { return MattersTable::configure($table); }

    /** Every listing, search and record lookup is limited to matters the user may see. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())->with(['client', 'lead', 'practiceArea']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['reference', 'title', 'case_number'];
    }

    public static function getRelations(): array
    {
        return array_values(array_filter([
            RelationManagers\AssignmentsRelationManager::class,
            RelationManagers\PartiesRelationManager::class,
            RelationManagers\EngagementsRelationManager::class,
            RelationManagers\NotesRelationManager::class,
            // Provided by the documents/tasks/calendar work; registered when present.
            ...array_filter([
                'App\\Filament\\Resources\\Matters\\RelationManagers\\HearingsRelationManager',
                'App\\Filament\\Resources\\Matters\\RelationManagers\\DeadlinesRelationManager',
                'App\\Filament\\Resources\\Matters\\RelationManagers\\TasksRelationManager',
                'App\\Filament\\Resources\\Matters\\RelationManagers\\DocumentsRelationManager',
            ], 'class_exists'),
            RelationManagers\StatusHistoryRelationManager::class,
        ]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMatters::route('/'),
            'create' => CreateMatter::route('/create'),
            'view' => ViewMatter::route('/{record}'),
            'edit' => EditMatter::route('/{record}/edit'),
        ];
    }
}
