<?php

namespace App\Filament\Resources\Matters\RelationManagers;

use App\Filament\Resources\Deadlines\DeadlineResource;
use App\Models\Deadline;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Deadlines tab on a matter. Register on MatterResource::getRelations(). */
class DeadlinesRelationManager extends RelationManager
{
    protected static string $relationship = 'deadlines';

    protected static ?string $title = 'Deadlines';

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
        return $schema->columns(2)->components(DeadlineResource::formFields(withMatter: false, fixedMatterId: $this->getOwnerRecord()->getKey()));
    }

    public function table(Table $table): Table
    {
        return DeadlineResource::tableFor($table, showMatter: false)
            ->headerActions([
                CreateAction::make()->label('Add deadline')
                    ->visible(fn () => Gate::allows('createFor', [Deadline::class, $this->getOwnerRecord()]))
                    ->using(function (array $data) {
                        Gate::authorize('createFor', [Deadline::class, $this->getOwnerRecord()]);

                        return $this->getOwnerRecord()->deadlines()->create($data);
                    }),
            ]);
    }
}
