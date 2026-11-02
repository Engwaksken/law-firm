<?php

namespace App\Filament\Resources\Consultations;

use App\Filament\Resources\Consultations\Pages\EditConsultation;
use App\Filament\Resources\Consultations\Pages\ListConsultations;
use App\Filament\Resources\Consultations\Schemas\ConsultationForm;
use App\Filament\Resources\Consultations\Tables\ConsultationsTable;
use App\Models\Consultation;
use App\Notifications\Admin\ConsultationStatusUpdated;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Notification;
use UnitEnum;

class ConsultationResource extends Resource
{
    protected static ?string $model = Consultation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Inbox';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Consultation requests';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->can('consultations.view')) {
            return null;
        }
        $count = Consultation::where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return ConsultationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Request')->columns(2)->columnSpanFull()->schema([
                TextEntry::make('reference')->copyable(),
                TextEntry::make('created_at')->label('Received')->dateTime(),
                TextEntry::make('name'),
                TextEntry::make('email')->copyable(),
                TextEntry::make('phone')->placeholder('—'),
                TextEntry::make('practiceArea.name')->label('Practice area')->placeholder('—'),
                TextEntry::make('consultation_type')->label('Format')->formatStateUsing(fn (?string $state) => Consultation::TYPES[$state] ?? $state),
                TextEntry::make('preferred')->label('Preferred')->state(fn (Consultation $r) => trim(($r->preferred_date?->format('D j M Y') ?? '').' '.($r->preferred_time ?? '')) ?: '—'),
                TextEntry::make('advocate.name')->label('Requested / assigned advocate')->placeholder('Any'),
                TextEntry::make('message')->columnSpanFull()->prose()->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return ConsultationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsultations::route('/'),
            'edit' => EditConsultation::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function confirmAction(): Action
    {
        return Action::make('confirm')
            ->label('Confirm')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Consultation $record) => in_array($record->status, ['pending', 'declined'], true) && auth()->user()->can('update', $record))
            ->fillForm(fn (Consultation $record) => [
                'scheduled_at' => $record->scheduled_at ?? $record->preferred_date?->setTime(10, 0),
                'advocate_id' => $record->advocate_id,
            ])
            ->schema([
                DateTimePicker::make('scheduled_at')->label('Date and time')->required()->seconds(false)->minDate(now()->startOfDay()),
                Select::make('advocate_id')->label('Advocate')
                    ->relationship('advocate', 'name', fn ($query) => $query->where('is_active', true))
                    ->preload(),
            ])
            ->modalDescription('The requester will receive a confirmation email with the date, time and advocate. Their original message is not included.')
            ->action(function (Consultation $record, array $data) {
                abort_unless(auth()->user()->can('update', $record), 403);
                $record->update(['status' => 'confirmed', 'scheduled_at' => $data['scheduled_at'], 'advocate_id' => $data['advocate_id'] ?? $record->advocate_id]);
                Notification::route('mail', $record->email)->notify(new ConsultationStatusUpdated($record->fresh(['advocate'])));
                FilamentNotification::make()->title('Consultation confirmed; requester notified')->success()->send();
            });
    }

    public static function declineAction(): Action
    {
        return Action::make('decline')
            ->label('Decline')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Consultation $record) => $record->status === 'pending' && auth()->user()->can('update', $record))
            ->schema([
                Textarea::make('reason')->label('Reason (sent to the requester, optional)')->rows(3)->maxLength(500),
            ])
            ->action(function (Consultation $record, array $data) {
                abort_unless(auth()->user()->can('update', $record), 403);
                $record->update(['status' => 'declined']);
                Notification::route('mail', $record->email)->notify(new ConsultationStatusUpdated($record, $data['reason'] ?? null));
                FilamentNotification::make()->title('Consultation declined; requester notified')->success()->send();
            });
    }
}
