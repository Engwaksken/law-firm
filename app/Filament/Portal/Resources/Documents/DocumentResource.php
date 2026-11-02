<?php

namespace App\Filament\Portal\Resources\Documents;

use App\Filament\Portal\PortalResource;
use App\Http\Controllers\Portal\PortalDocumentController;
use App\Models\Document;
use App\Support\PortalAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Documents the firm shared with the client, and the client's own uploads. */
class DocumentResource extends PortalResource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Documents';

    protected static ?string $slug = 'documents';

    public static function portalQuery(): Builder
    {
        return PortalAccess::documents(auth()->user())->with(['matter', 'currentVersion']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->wrap()
                    ->description(fn (Document $record) => $record->uploaded_by_client ? 'Uploaded by you' : null),
                TextColumn::make('matter.reference')->label('Matter')->badge(),
                TextColumn::make('category')->formatStateUsing(fn (string $state) => Document::CATEGORIES[$state] ?? $state),
                TextColumn::make('currentVersion.original_name')->label('File')->toggleable(),
                TextColumn::make('shared_with_client_at')->label('Shared')->date()->placeholder('—')->sortable(),
            ])
            ->defaultSort('shared_with_client_at', 'desc')
            ->filters([
                SelectFilter::make('matter')->label('Matter')
                    ->options(fn () => PortalAccess::matters(auth()->user())->get()->mapWithKeys(fn ($matter) => [$matter->id => $matter->reference.' · '.$matter->title]))
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, $id) => $query->where('documents.matter_id', $id))),
            ])
            ->recordActions([
                Action::make('download')->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (Document $record) => $record->currentVersion ? PortalDocumentController::temporaryUrl($record->currentVersion) : null)
                    ->visible(fn (Document $record) => $record->currentVersion?->isDownloadable() ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListDocuments::route('/')];
    }
}
