<?php

namespace App\Filament\Resources\ConflictChecks;

use App\Filament\Resources\ConflictChecks\Pages\CreateConflictCheck;
use App\Filament\Resources\ConflictChecks\Pages\ListConflictChecks;
use App\Filament\Resources\ConflictChecks\Pages\ViewConflictCheck;
use App\Filament\Resources\ConflictChecks\Schemas\ConflictCheckForm;
use App\Filament\Resources\ConflictChecks\Schemas\ConflictCheckInfolist;
use App\Filament\Resources\ConflictChecks\Tables\ConflictChecksTable;
use App\Models\ConflictCheck;
use App\Models\Client;
use App\Models\Matter;
use App\Legal\ConflictSearch;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use Illuminate\Support\Facades\Gate;

/** Conflict checks are immutable once run; only the review decision is added. */
class ConflictCheckResource extends Resource
{
    protected static ?string $model = ConflictCheck::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|UnitEnum|null $navigationGroup = 'Legal Practice';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Schema $schema): Schema { return ConflictCheckForm::configure($schema); }

    public static function infolist(Schema $schema): Schema { return ConflictCheckInfolist::configure($schema); }

    public static function table(Table $table): Table { return ConflictChecksTable::configure($table); }

    public static function runModalAction(string $name = 'runConflictCheck'): Action
    {
        return Action::make($name)
            ->label('Run conflict check')
            ->icon(Heroicon::OutlinedShieldExclamation)
            ->color('warning')
            ->schema(fn (Schema $schema): Schema => static::form($schema))
            ->action(function (array $data): void {
                static::runCheck($data);
                Notification::make()->title('Conflict check completed')->success()->send();
            });
    }

    public static function runCheck(array $data): ConflictCheck
    {
        $matter = filled($data['matter_id'] ?? null) ? Matter::find($data['matter_id']) : null;
        abort_if($matter && ! Gate::allows('view', $matter), 403);

        return app(ConflictSearch::class)->run(
            $data['parties'] ?? [],
            auth()->user(),
            filled($data['client_id'] ?? null) ? Client::find($data['client_id']) : null,
            $matter,
        );
    }

    /** @return array<string, mixed> */
    public static function initialFormData(?Client $client = null, ?Matter $matter = null): array
    {
        if ($matter && ! Gate::allows('view', $matter)) {
            $matter = null;
        }
        $client ??= $matter?->client;

        $parties = [];
        if ($client) {
            $parties[] = ['name' => $client->name, 'role' => 'prospective_client'];
            foreach ($client->contacts as $contact) {
                $parties[] = ['name' => $contact->name, 'role' => 'related'];
            }
        }
        foreach ($matter?->parties ?? [] as $party) {
            $parties[] = ['name' => $party->name, 'role' => in_array($party->party_type, ['opposing', 'opposing_counsel'], true) ? 'opposing' : 'related'];
        }

        return [
            'client_id' => $client?->id,
            'matter_id' => $matter?->id,
            'parties' => $parties ?: [['name' => '', 'role' => 'prospective_client']],
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->can('conflicts.review')) {
            return null;
        }
        $count = ConflictCheck::where('status', 'pending_review')->where('requested_by', '!=', auth()->id())->count();

        return $count ? (string) $count : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConflictChecks::route('/'),
            'view' => ViewConflictCheck::route('/{record}'),
        ];
    }
}
