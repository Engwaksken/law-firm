<?php

namespace App\Filament\Resources\Events;

use App\Filament\Forms\MediaPicker;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use UnitEnum;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Group::make()->columnSpan(['lg' => 2])->schema([
                Section::make('Event')->schema([
                    TextInput::make('title')->required()->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, ?string $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')->required()->maxLength(255)->alphaDash()->unique(ignoreRecord: true),
                    Textarea::make('summary')->rows(2)->maxLength(300),
                    RichEditor::make('description'),
                ]),
            ]),
            Group::make()->columnSpan(['lg' => 1])->schema([
                Section::make('When & where')->schema([
                    DateTimePicker::make('starts_at')->required()->seconds(false),
                    DateTimePicker::make('ends_at')->seconds(false)->afterOrEqual('starts_at'),
                    TextInput::make('location')->maxLength(255),
                    Toggle::make('is_online')->label('Online event'),
                    TextInput::make('registration_url')->label('Registration link')->url()->maxLength(500)
                        ->rule('regex:#^https?://#i')
                        ->validationMessages(['regex' => 'The link must start with http:// or https://.']),
                ]),
                Section::make('Publishing')->schema([
                    Select::make('status')->options(Event::STATUSES)->default('draft')->required()
                        ->helperText(fn () => auth()->user()->can('content.publish') ? 'Cancelled events stay visible with a cancellation notice.' : 'You can save drafts only; an editor with publishing rights must publish.'),
                    DateTimePicker::make('published_at')->label('Publish at')->seconds(false)
                        ->helperText('Defaults to now when published.'),
                    MediaPicker::make('image_id', 'Image'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('starts_at')->label('Date')->dateTime('j M Y H:i')->sortable(),
                TextColumn::make('title')->searchable()->limit(60)->description(fn (Event $record) => $record->location),
                TextColumn::make('state')->label('Status')->badge()
                    ->state(fn (Event $record) => match (true) {
                        $record->status === 'draft' => 'Draft',
                        $record->status === 'cancelled' => 'Cancelled',
                        $record->published_at?->isFuture() => 'Scheduled',
                        $record->isPast() => 'Past',
                        default => 'Upcoming',
                    })
                    ->color(fn (string $state) => ['Draft' => 'gray', 'Cancelled' => 'danger', 'Scheduled' => 'warning', 'Past' => 'gray', 'Upcoming' => 'success'][$state]),
            ])
            ->filters([
                SelectFilter::make('status')->options(Event::STATUSES),
                SelectFilter::make('when')->options(['upcoming' => 'Upcoming', 'past' => 'Past'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'upcoming' => $query->where('starts_at', '>=', now()->startOfDay()),
                        'past' => $query->where('starts_at', '<', now()->startOfDay()),
                        default => $query,
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make(), RestoreAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make(), RestoreBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    /**
     * Server-side publishing guard. Without content.publish an event can't become
     * published or cancelled (both are public); an already public one keeps its status.
     */
    public static function enforcePublishingRules(array $data, ?Event $existing = null): array
    {
        if (! auth()->user()->can('content.publish')) {
            if ($existing && $existing->status !== 'draft') {
                $data['status'] = $existing->status;
                $data['published_at'] = $existing->published_at;

                return $data;
            }
            $data['status'] = 'draft';
        }

        if (in_array($data['status'] ?? null, ['published', 'cancelled'], true) && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
