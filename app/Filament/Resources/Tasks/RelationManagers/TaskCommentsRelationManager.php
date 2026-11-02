<?php

namespace App\Filament\Resources\Tasks\RelationManagers;

use App\Models\Task;
use App\Models\TaskComment;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class TaskCommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Comments';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('author.name')->label('By')->placeholder('—'),
                TextColumn::make('body')->label('Comment')->wrap(),
                TextColumn::make('created_at')->label('When')->since(),
            ])
            ->defaultSort('created_at')
            ->paginated(false)
            ->headerActions([
                Action::make('comment')->label('Add comment')
                    ->visible(fn () => Gate::allows('comment', $this->getOwnerRecord()))
                    ->schema([Textarea::make('body')->label('Comment')->required()->rows(3)->maxLength(5000)])
                    ->action(function (array $data) {
                        /** @var Task $task */
                        $task = $this->getOwnerRecord();
                        Gate::authorize('comment', $task);
                        $task->comments()->create(['body' => $data['body']]);
                    }),
            ])
            ->recordActions([
                Action::make('delete')->icon('heroicon-o-trash')->color('danger')->iconButton()
                    ->visible(fn (TaskComment $record) => $record->user_id === auth()->id())
                    ->requiresConfirmation()
                    ->action(function (TaskComment $record) {
                        abort_unless($record->user_id === auth()->id(), 403);
                        $record->delete();
                    }),
            ]);
    }
}
