<?php

namespace App\Filament\Resources\PracticeAreas;

use App\Filament\Resources\PracticeAreas\Pages\CreatePracticeArea;
use App\Filament\Resources\PracticeAreas\Pages\EditPracticeArea;
use App\Filament\Resources\PracticeAreas\Pages\ListPracticeAreas;
use App\Filament\Resources\PracticeAreas\Schemas\PracticeAreaForm;
use App\Filament\Resources\PracticeAreas\Tables\PracticeAreasTable;
use App\Models\PracticeArea;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PracticeAreaResource extends Resource
{
    protected static ?string $model = PracticeArea::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PracticeAreaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PracticeAreasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPracticeAreas::route('/'),
            'create' => CreatePracticeArea::route('/create'),
            'edit' => EditPracticeArea::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
