<?php

namespace App\Filament\Resources\Appointments;

use App\Filament\Resources\Appointments\Pages\ManageAppointments;
use App\Filament\Support\CalendarFields;
use App\Filament\Support\MatterOptions;
use App\Models\Appointment;
use App\Models\Client;
use BackedEnum;
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
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Legal Practice';

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())->with(['matter', 'organizer']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->required()->maxLength(200),
            Select::make('type')->options(Appointment::TYPES)->default('client_meeting')->required(),
            CalendarFields::matter(Appointment::class, required: false)->label('Matter (optional)'),
            Select::make('client_id')->label('Client (optional)')->searchable()
                ->visible(fn () => auth()->user()->can('clients.view'))
                ->options(fn () => Client::query()->orderBy('name')->limit(500)->pluck('name', 'id')),
            DateTimePicker::make('starts_at')->label('Starts')->seconds(false)->required(),
            DateTimePicker::make('ends_at')->label('Ends')->seconds(false)->after('starts_at'),
            TextInput::make('location')->maxLength(200),
            Select::make('attendee_ids')->label('Attendees (staff)')->multiple()->searchable()
                ->options(fn () => MatterOptions::teamOptions(null)),
            Textarea::make('notes')->rows(2)->columnSpanFull(),
            CalendarFields::reminders(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('starts_at')->label('When')->dateTime('D j M Y, H:i')->sortable(),
                TextColumn::make('title')->searchable(),
                TextColumn::make('type')->badge()->color('gray')->formatStateUsing(fn (string $state) => Appointment::TYPES[$state] ?? $state),
                TextColumn::make('matter.reference')->label('Matter')->placeholder('—'),
                TextColumn::make('location')->placeholder('—')->toggleable(),
                TextColumn::make('organizer.name')->label('Organiser'),
            ])
            ->defaultSort('starts_at')
            ->filters([
                Filter::make('upcoming')->default()->query(fn (Builder $query) => $query->where('starts_at', '>=', now()->startOfDay())),
                Filter::make('mine')->label('Mine')->query(fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->where('organizer_id', auth()->id())->orWhereJsonContains('attendee_ids', auth()->id())->orWhereJsonContains('attendee_ids', (string) auth()->id()))),
                SelectFilter::make('type')->options(Appointment::TYPES),
            ])
            ->recordActions([
                EditAction::make()->using(function (Appointment $record, array $data) {
                    Gate::authorize('update', $record);
                    if (($data['matter_id'] ?? null) !== $record->matter_id) {
                        MatterOptions::authorizeFor(Appointment::class, $data['matter_id'] ?? null);
                    }
                    $record->update($data);

                    return $record;
                }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAppointments::route('/')];
    }
}
