<?php

namespace App\Filament\Resources\Tasks;

use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\RelationManagers\TaskCommentsRelationManager;
use App\Filament\Resources\Tasks\RelationManagers\TaskDocumentsRelationManager;
use App\Filament\Support\MatterOptions;
use App\Models\Task;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Legal Practice';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())->with(['matter', 'assignee']);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class])->visibleTo(auth()->user());
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Task::query()->where('assigned_to', auth()->id())->overdue()->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'My overdue tasks';
    }

    /** @param bool $withMatter false inside a matter's relation manager (matter is fixed) */
    public static function formFields(bool $withMatter = true, ?int $fixedMatterId = null): array
    {
        return [
            Section::make()->columns(2)->columnSpanFull()->schema(array_filter([
                TextInput::make('title')->required()->maxLength(200)->columnSpanFull(),
                $withMatter ? Select::make('matter_id')->label('Matter (optional)')->searchable()->live()
                    ->options(fn (?Task $record) => MatterOptions::contributable(Task::class)
                        + ($record?->matter ? [$record->matter_id => $record->matter->label()] : []))
                    ->afterStateUpdated(fn (Set $set) => $set('assigned_to', null)) : null,
                Select::make('assigned_to')->label('Assign to')->searchable()
                    ->options(fn (Get $get) => MatterOptions::teamOptions($fixedMatterId ?? $get('matter_id')))
                    ->in(fn (Get $get) => array_keys(MatterOptions::teamOptions($fixedMatterId ?? $get('matter_id'))))
                    ->helperText(fn (Get $get) => ($fixedMatterId ?? $get('matter_id')) ? 'Only people on the matter team.' : null),
                DateTimePicker::make('due_at')->label('Due')->seconds(false),
                Select::make('priority')->options(Task::PRIORITIES)->default('normal')->required(),
                Select::make('status')->options(Task::STATUSES)->default('open')->required(),
                Textarea::make('description')->rows(4)->columnSpanFull(),
            ])),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::formFields());
    }

    public static function table(Table $table): Table
    {
        return static::tableFor($table, showMatter: true);
    }

    public static function tableFor(Table $table, bool $showMatter): Table
    {
        return $table
            ->columns(array_filter([
                TextColumn::make('title')->searchable()->sortable()->wrap(),
                $showMatter ? TextColumn::make('matter.reference')->label('Matter')->placeholder('Personal') : null,
                TextColumn::make('assignee.name')->label('Assigned to')->placeholder('Unassigned'),
                TextColumn::make('due_at')->label('Due')->dateTime('j M Y, H:i')->sortable()
                    ->color(fn (Task $record) => $record->isOverdue() ? 'danger' : null)
                    ->description(fn (Task $record) => $record->isOverdue() ? 'Overdue' : null),
                TextColumn::make('priority')->badge()->formatStateUsing(fn (string $state) => Task::PRIORITIES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'urgent' => 'danger', 'high' => 'warning', default => 'gray' }),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => Task::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'done' => 'success', 'blocked' => 'danger', 'in_progress' => 'info', default => 'gray' }),
            ]))
            ->defaultSort('due_at')
            ->filters(array_filter([
                Filter::make('mine')->label('My tasks')->query(fn (Builder $query) => $query->where('assigned_to', auth()->id())),
                Filter::make('created_by_me')->label('Created by me')->query(fn (Builder $query) => $query->where('created_by', auth()->id())),
                Filter::make('overdue')->query(fn (Builder $query) => $query->overdue()),
                Filter::make('open')->label('Not done')->default()->query(fn (Builder $query) => $query->where('status', '!=', 'done')),
                SelectFilter::make('status')->options(Task::STATUSES),
                SelectFilter::make('priority')->options(Task::PRIORITIES),
                $showMatter ? SelectFilter::make('matter_id')->label('Matter')->options(fn () => MatterOptions::visible())->searchable() : null,
                TrashedFilter::make(),
            ]))
            ->recordActions([
                Action::make('complete')->label('Mark done')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (Task $record) => $record->status !== 'done' && auth()->user()->can('update', $record))
                    ->action(function (Task $record) {
                        Gate::authorize('update', $record);
                        $record->update(['status' => 'done']);
                    }),
                EditAction::make()->url(fn (Task $record) => static::getUrl('edit', ['record' => $record])),
            ]);
    }

    public static function getRelations(): array
    {
        return [TaskCommentsRelationManager::class, TaskDocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/create'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }
}
