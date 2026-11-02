<?php

namespace App\Filament\Resources\Matters\RelationManagers;

use App\Filament\Resources\Hearings\HearingResource;
use App\Models\Hearing;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Hearings tab on a matter. Register on MatterResource::getRelations(). */
class HearingsRelationManager extends RelationManager
{
    protected static string $relationship = 'hearings';

    protected static ?string $title = 'Hearings';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(HearingResource::formFields(withMatter: false));
    }

    public function table(Table $table): Table
    {
        return HearingResource::tableFor($table, showMatter: false)
            ->filters([])
            ->headerActions([
                CreateAction::make()->label('Add hearing')
                    ->visible(fn () => Gate::allows('createFor', [Hearing::class, $this->getOwnerRecord()]))
                    ->using(function (array $data) {
                        Gate::authorize('createFor', [Hearing::class, $this->getOwnerRecord()]);

                        return $this->getOwnerRecord()->hearings()->create($data);
                    }),
            ]);
    }
}
