<?php

namespace App\Filament\Resources\Matters\RelationManagers;

use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Tasks tab on a matter. Register on MatterResource::getRelations(). */
class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Tasks';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('view', $ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(TaskResource::formFields(withMatter: false, fixedMatterId: $this->getOwnerRecord()->getKey()));
    }

    public function table(Table $table): Table
    {
        return TaskResource::tableFor($table, showMatter: false)
            ->headerActions([
                CreateAction::make()->label('Add task')
                    ->visible(fn () => Gate::allows('createFor', [Task::class, $this->getOwnerRecord()]))
                    ->using(function (array $data) {
                        Gate::authorize('createFor', [Task::class, $this->getOwnerRecord()]);
                        $task = $this->getOwnerRecord()->tasks()->create($data);
                        CreateTask::notifyAssignee($task);

                        return $task;
                    }),
            ]);
    }
}
