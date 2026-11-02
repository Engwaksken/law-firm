<?php

namespace App\Filament\Resources\Advocates;

use App\Filament\Resources\Advocates\Pages\CreateAdvocate;
use App\Filament\Resources\Advocates\Pages\EditAdvocate;
use App\Filament\Resources\Advocates\Pages\ListAdvocates;
use App\Filament\Resources\Advocates\Schemas\AdvocateForm;
use App\Filament\Resources\Advocates\Tables\AdvocatesTable;
use App\Models\Advocate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AdvocateResource extends Resource
{
    protected static ?string $model = Advocate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Advocate profiles';

    protected static ?string $recordTitleAttribute = 'name';

    /** Fields whose change invalidates a prior credentials verification. */
    public const CREDENTIAL_FIELDS = ['title', 'qualifications', 'memberships', 'experience'];

    public static function form(Schema $schema): Schema
    {
        return AdvocateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdvocatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdvocates::route('/'),
            'create' => CreateAdvocate::route('/create'),
            'edit' => EditAdvocate::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
