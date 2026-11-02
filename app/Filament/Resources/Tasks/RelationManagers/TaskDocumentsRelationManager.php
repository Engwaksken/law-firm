<?php

namespace App\Filament\Resources\Tasks\RelationManagers;

use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Models\Document;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Documents attached to a task. Only possible for matter tasks (documents must belong to a matter). */
class TaskDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Attachments';

    public function table(Table $table): Table
    {
        /** @var Task $task */
        $task = $this->getOwnerRecord();
        $canAttach = fn () => $task->matter !== null && Gate::allows('createFor', [Document::class, $task->matter]);

        return DocumentResource::tableFor($table, showMatter: false)
            ->modifyQueryUsing(fn (Builder $query) => $query->visibleTo(auth()->user()))
            ->headerActions([
                Action::make('upload')->label('Upload attachment')->icon(Heroicon::OutlinedArrowUpTray)
                    ->visible($canAttach)
                    ->schema(DocumentResource::uploadFields(withOwner: false))
                    ->action(function (array $data) use ($task) {
                        Gate::authorize('createFor', [Document::class, $task->matter]);
                        CreateDocument::storeUpload($data, 'mountedActions.0.data.file',
                            ['matter_id' => $task->matter_id, 'client_id' => null, 'is_template' => false, 'task_id' => $task->id]);
                    }),
                Action::make('link')->label('Link existing document')->icon(Heroicon::OutlinedLink)->color('gray')
                    ->visible($canAttach)
                    ->schema([
                        Select::make('document_id')->label('Document')->searchable()->required()
                            ->options(fn () => Document::query()->visibleTo(auth()->user())
                                ->where('matter_id', $task->matter_id)->whereNull('task_id')->pluck('title', 'id')),
                    ])
                    ->action(function (array $data) use ($task) {
                        $document = Document::query()->visibleTo(auth()->user())->where('matter_id', $task->matter_id)->findOrFail($data['document_id']);
                        Gate::authorize('update', $document);
                        $document->forceFill(['task_id' => $task->id])->save();
                    }),
            ]);
    }
}
