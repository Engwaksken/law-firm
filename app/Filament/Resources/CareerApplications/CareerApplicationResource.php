<?php

namespace App\Filament\Resources\CareerApplications;

use App\Filament\Resources\CareerApplications\Pages\EditCareerApplication;
use App\Filament\Resources\CareerApplications\Pages\ListCareerApplications;
use App\Filament\Resources\CareerApplications\Schemas\CareerApplicationForm;
use App\Filament\Resources\CareerApplications\Tables\CareerApplicationsTable;
use App\Models\ActivityLog;
use App\Models\CareerApplication;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class CareerApplicationResource extends Resource
{
    protected static ?string $model = CareerApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Careers';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Applications';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->can('careers.manage')) {
            return null;
        }
        $count = CareerApplication::where('status', 'received')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return CareerApplicationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Applicant')->columns(2)->columnSpanFull()->schema([
                TextEntry::make('reference')->copyable(),
                TextEntry::make('created_at')->label('Received')->dateTime(),
                TextEntry::make('career.title')->label('Position'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (string $state) => CareerApplication::STATUSES[$state] ?? $state),
                TextEntry::make('name'),
                TextEntry::make('email')->copyable(),
                TextEntry::make('phone')->placeholder('—'),
                TextEntry::make('cv_original_name')->label('CV file'),
                TextEntry::make('cover_letter')->columnSpanFull()->prose()->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return CareerApplicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCareerApplications::route('/'),
            'edit' => EditCareerApplication::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    /** Streams the CV from private storage after an authorisation check; each download is audited. */
    public static function downloadCvAction(): Action
    {
        return Action::make('downloadCv')
            ->label('Download CV')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->visible(fn (CareerApplication $record) => Gate::allows('downloadCv', $record))
            ->action(function (CareerApplication $record) {
                Gate::authorize('downloadCv', $record);

                $disk = Storage::disk(CareerApplication::CV_DISK);
                abort_unless($disk->exists($record->cv_path), 404);

                ActivityLog::record('application.cv-downloaded', $record, 'CV downloaded for '.$record->reference);

                return $disk->download($record->cv_path, $record->cv_original_name);
            });
    }
}
