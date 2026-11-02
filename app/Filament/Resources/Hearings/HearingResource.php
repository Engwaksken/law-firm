<?php

namespace App\Filament\Resources\Hearings;

use App\Filament\Resources\Hearings\Pages\ManageHearings;
use App\Filament\Support\CalendarFields;
use App\Filament\Support\MatterOptions;
use App\Models\Court;
use App\Models\Hearing;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class HearingResource extends Resource
{
    protected static ?string $model = Hearing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Legal Practice';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())->with(['matter', 'court']);
    }

    public static function formFields(bool $withMatter = true): array
    {
        return array_filter([
            $withMatter ? CalendarFields::matter(Hearing::class) : null,
            Select::make('type')->options(Hearing::TYPES)->default('hearing')->required(),
            Select::make('court_id')->label('Court / tribunal')->searchable()
                ->options(fn () => Court::query()->where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn (Court $c) => [$c->id => $c->label()])),
            DateTimePicker::make('starts_at')->label('Date & time')->seconds(false)->required(),
            DateTimePicker::make('ends_at')->label('Ends')->seconds(false)->after('starts_at'),
            TextInput::make('courtroom')->maxLength(80),
            TextInput::make('judicial_officer')->label('Judge / judicial officer')->maxLength(120),
            Select::make('status')->options(Hearing::STATUSES)->default('scheduled')->required(),
            TextInput::make('title')->label('Description (optional)')->maxLength(200),
            Textarea::make('outcome')->label('Outcome / notes')->rows(3)->columnSpanFull()
                ->helperText('Stored encrypted. Visible only to people with access to the matter.'),
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
                TextColumn::make('starts_at')->label('When')->dateTime('D j M Y, H:i')->sortable(),
                TextColumn::make('type')->badge()->formatStateUsing(fn (string $state) => Hearing::TYPES[$state] ?? $state),
                $showMatter ? TextColumn::make('matter.reference')->label('Matter')->description(fn (Hearing $record) => $record->matter?->title) : null,
                TextColumn::make('court.name')->label('Court')->placeholder('—')->description(fn (Hearing $record) => $record->courtroom),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => Hearing::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'completed' => 'success', 'adjourned' => 'warning', 'cancelled' => 'gray', default => 'info' }),
            ]))
            ->defaultSort('starts_at')
            ->filters(array_filter([
                Filter::make('upcoming')->default()->query(fn (Builder $query) => $query->where('starts_at', '>=', now()->startOfDay())),
                SelectFilter::make('status')->options(Hearing::STATUSES),
                SelectFilter::make('type')->options(Hearing::TYPES),
                $showMatter ? SelectFilter::make('matter_id')->label('Matter')->options(fn () => MatterOptions::visible())->searchable() : null,
            ]))
            ->recordActions([
                static::adjournAction(),
                EditAction::make()->using(function (Hearing $record, array $data) {
                    Gate::authorize('update', $record);
                    unset($data['matter_id']);
                    $record->update($data);

                    return $record;
                }),
                DeleteAction::make(),
            ]);
    }

    /** Mark adjourned and optionally schedule the next date in one step. */
    public static function adjournAction(): Action
    {
        return Action::make('adjourn')->icon(Heroicon::OutlinedForward)->color('warning')
            ->visible(fn (Hearing $record) => $record->status === 'scheduled' && auth()->user()->can('update', $record))
            ->schema([
                DateTimePicker::make('next_at')->label('Adjourned to (optional)')->seconds(false)->after('now'),
                Textarea::make('reason')->label('Reason / notes')->rows(2),
            ])
            ->action(function (Hearing $record, array $data) {
                Gate::authorize('update', $record);
                DB::transaction(function () use ($record, $data) {
                    $record->update([
                        'status' => 'adjourned',
                        'outcome' => trim(($record->outcome ? $record->outcome."\n" : '').'Adjourned'.(filled($data['reason'] ?? null) ? ': '.$data['reason'] : '')),
                    ]);
                    if (filled($data['next_at'] ?? null)) {
                        Hearing::create([
                            'matter_id' => $record->matter_id, 'court_id' => $record->court_id, 'type' => $record->type,
                            'title' => $record->title, 'starts_at' => $data['next_at'], 'courtroom' => $record->courtroom,
                            'judicial_officer' => $record->judicial_officer, 'status' => 'scheduled', 'reminder_days' => $record->reminder_days,
                        ]);
                    }
                });
            });
    }

    public static function getPages(): array
    {
        return ['index' => ManageHearings::route('/')];
    }
}
