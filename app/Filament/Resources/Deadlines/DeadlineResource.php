<?php

namespace App\Filament\Resources\Deadlines;

use App\Filament\Resources\Deadlines\Pages\ManageDeadlines;
use App\Filament\Support\CalendarFields;
use App\Filament\Support\MatterOptions;
use App\Models\Deadline;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class DeadlineResource extends Resource
{
    protected static ?string $model = Deadline::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Legal Practice';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())->with(['matter', 'responsible']);
    }

    public static function formFields(bool $withMatter = true, ?int $fixedMatterId = null): array
    {
        return array_filter([
            $withMatter ? CalendarFields::matter(Deadline::class) : null,
            TextInput::make('title')->required()->maxLength(200),
            Select::make('type')->options(Deadline::TYPES)->default('filing')->required(),
            DateTimePicker::make('due_at')->label('Due')->seconds(false)->required()
                ->helperText('Enter the date yourself. The system does not calculate statutory deadlines. Verify with a qualified advocate.'),
            Select::make('responsible_user_id')->label('Responsible')->searchable()
                ->options(fn (Get $get) => MatterOptions::teamOptions($fixedMatterId ?? $get('matter_id')))
                ->in(fn (Get $get) => array_keys(MatterOptions::teamOptions($fixedMatterId ?? $get('matter_id')))),
            Textarea::make('notes')->rows(2)->columnSpanFull(),
            CalendarFields::reminders(),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(static::formFields());
    }

    public static function table(Table $table): Table
    {
        return static::tableFor($table, showMatter: true);
    }

    public static function tableFor(Table $table, bool $showMatter): Table
    {
        return $table
            ->columns(array_filter([
                TextColumn::make('due_at')->label('Due')->dateTime('D j M Y, H:i')->sortable()
                    ->color(fn (Deadline $record) => $record->isOverdue() ? 'danger' : null)
                    ->description(fn (Deadline $record) => $record->completed_at ? 'Completed' : ($record->isOverdue() ? 'Overdue' : null)),
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('type')->badge()->color('gray')->formatStateUsing(fn (string $state) => Deadline::TYPES[$state] ?? $state),
                $showMatter ? TextColumn::make('matter.reference')->label('Matter') : null,
                TextColumn::make('responsible.name')->label('Responsible')->placeholder('Lead advocate'),
            ]))
            ->defaultSort('due_at')
            ->filters(array_filter([
                Filter::make('outstanding')->default()->query(fn (Builder $query) => $query->whereNull('completed_at')),
                Filter::make('mine')->label('Mine')->query(fn (Builder $query) => $query->where('responsible_user_id', auth()->id())),
                SelectFilter::make('type')->options(Deadline::TYPES),
                $showMatter ? SelectFilter::make('matter_id')->label('Matter')->options(fn () => MatterOptions::visible())->searchable() : null,
            ]))
            ->recordActions([
                Action::make('complete')->label('Mark complete')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (Deadline $record) => $record->completed_at === null && auth()->user()->can('update', $record))
                    ->requiresConfirmation()
                    ->action(function (Deadline $record) {
                        Gate::authorize('update', $record);
                        $record->forceFill(['completed_at' => now(), 'completed_by' => auth()->id()])->save();
                    }),
                EditAction::make()->using(function (Deadline $record, array $data) {
                    Gate::authorize('update', $record);
                    unset($data['matter_id']);
                    $record->update($data);

                    return $record;
                }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDeadlines::route('/')];
    }
}
