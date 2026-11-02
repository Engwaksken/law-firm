<?php

namespace App\Filament\Resources\TimeEntries;

use App\Filament\Resources\TimeEntries\Pages\ManageTimeEntries;
use App\Filament\Support\MatterOptions;
use App\Finance\ExpenseService;
use App\Models\Engagement;
use App\Models\Matter;
use App\Models\TimeEntry;
use App\Support\Money;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class TimeEntryResource extends Resource
{
    protected static ?string $model = TimeEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Time entries';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())->with(['matter', 'user']);
    }

    /** Default rate: the approved engagement's hourly rate, else the user's default rate. */
    public static function defaultRate(?int $matterId): ?string
    {
        $engagementRate = $matterId ? Engagement::query()->where('matter_id', $matterId)->where('status', 'approved')
            ->where('fee_arrangement', 'hourly')->latest('approved_at')->value('hourly_rate') : null;

        return $engagementRate ?? auth()->user()->default_hourly_rate;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('matter_id')->label('Matter')->required()->searchable()
                ->options(fn () => MatterOptions::contributable(TimeEntry::class))
                ->live()
                ->afterStateUpdated(fn (Set $set, ?int $state) => $set('rate', static::defaultRate($state)))
                ->disabledOn('edit'),
            DatePicker::make('worked_on')->label('Date')->required()->default(today())->maxDate(today()),
            TextInput::make('hours')->label('Time (hours)')->required()->numeric()->minValue(0.05)->maxValue(24)->step(0.05)
                ->helperText('Decimal hours, e.g. 1.5 = 1 h 30 min')
                ->formatStateUsing(fn (?TimeEntry $record) => $record ? round($record->minutes / 60, 2) : null)
                ->dehydrated(false),
            TextInput::make('rate')->label('Hourly rate')->numeric()->minValue(0)->required()
                ->prefix(fn () => Settings::get('default_currency'))
                ->default(fn (Get $get) => static::defaultRate($get('matter_id')))
                ->disabled(fn () => ! auth()->user()->can('invoices.issue') && ! auth()->user()->can('matters.assign'))
                ->dehydrated(),
            Toggle::make('billable')->default(true)->inline(false),
            Textarea::make('description')->label('Work done')->required()->rows(3)->maxLength(1000)->columnSpanFull()
                ->helperText('Appears on the invoice. Keep it factual and avoid confidential detail.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('worked_on')->label('Date')->date()->sortable(),
                TextColumn::make('user.name')->label('By')->sortable(),
                TextColumn::make('matter.reference')->label('Matter')->searchable(),
                TextColumn::make('description')->limit(50)->wrap()->searchable(),
                TextColumn::make('minutes')->label('Hours')->formatStateUsing(fn (int $state) => number_format($state / 60, 2))
                    ->summarize(Sum::make()->label('Total h')->formatStateUsing(fn ($state) => number_format(((int) $state) / 60, 2))),
                TextColumn::make('amount')->formatStateUsing(fn ($state, TimeEntry $record) => Money::format($state, $record->currency))
                    ->summarize(Sum::make()->label('Total')->money(Settings::get('default_currency'), decimalPlaces: 0)),
                IconColumn::make('billable')->boolean(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => TimeEntry::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'billed' => 'success', 'written_off' => 'gray', default => 'warning' }),
            ])
            ->defaultSort('worked_on', 'desc')
            ->filters([
                Filter::make('mine')->label('My time')->default()->query(fn (Builder $query) => $query->where('user_id', auth()->id())),
                SelectFilter::make('status')->options(TimeEntry::STATUSES),
                SelectFilter::make('matter_id')->label('Matter')->options(fn () => MatterOptions::visible())->searchable(),
                Filter::make('worked_on')->schema([DatePicker::make('from'), DatePicker::make('until')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($query, $d) => $query->whereDate('worked_on', '>=', $d))
                        ->when($data['until'] ?? null, fn ($query, $d) => $query->whereDate('worked_on', '<=', $d))),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (TimeEntry $record) => auth()->user()->can('update', $record))
                    ->authorize(fn (TimeEntry $record) => auth()->user()->can('update', $record))
                    ->mutateRecordDataUsing(fn (array $data, TimeEntry $record) => $data + ['hours' => round($record->minutes / 60, 2)])
                    ->using(function (TimeEntry $record, array $data, array $arguments, $livewire) {
                        $hours = $livewire->mountedActions[0]['data']['hours'] ?? null;
                        $record->update(collect($data)->except(['matter_id'])->all() + ($hours ? ['minutes' => (int) round(((float) $hours) * 60)] : []));

                        return $record;
                    }),
                DeleteAction::make()
                    ->visible(fn (TimeEntry $record) => auth()->user()->can('delete', $record))
                    ->authorize(fn (TimeEntry $record) => auth()->user()->can('delete', $record)),
            ])
            ->toolbarActions([
                BulkAction::make('writeOff')->label('Write off')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->visible(fn () => auth()->user()->can('invoices.issue'))
                    ->schema([Textarea::make('reason')->required()])
                    ->requiresConfirmation()
                    ->action(function (Collection $records, array $data) {
                        $done = $records->filter(fn (TimeEntry $entry) => auth()->user()->can('writeOff', $entry))
                            ->each(fn (TimeEntry $entry) => app(ExpenseService::class)->writeOff($entry, auth()->user(), $data['reason']))->count();
                        Notification::make()->title("{$done} entr".($done === 1 ? 'y' : 'ies').' written off')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTimeEntries::route('/')];
    }
}
