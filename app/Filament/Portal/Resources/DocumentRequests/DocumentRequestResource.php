<?php

namespace App\Filament\Portal\Resources\DocumentRequests;

use App\Filament\Portal\PortalResource;
use App\Filament\Resources\Matters\MatterResource as StaffMatterResource;
use App\Legal\DocumentService;
use App\Models\DocumentRequest;
use App\Portal\PortalNotifier;
use App\Support\PortalAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** Documents the firm asked the client for; the client uploads them here. */
class DocumentRequestResource extends PortalResource
{
    protected static ?string $model = DocumentRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Requested documents';

    protected static ?string $slug = 'document-requests';

    public const MAX_UPLOAD_KB = 20480;

    public static function portalQuery(): Builder
    {
        return PortalAccess::documentRequests(auth()->user())->with('matter');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = PortalAccess::documentRequests(auth()->user())->where('status', 'open')->count();

        return $count ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->wrap()->description(fn (DocumentRequest $record) => $record->description),
                TextColumn::make('matter.reference')->label('Matter')->badge(),
                TextColumn::make('due_on')->label('Due')->date()->placeholder('—'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => DocumentRequest::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'open' => 'warning', 'submitted' => 'info', 'accepted' => 'success', default => 'gray' }),
            ])
            ->defaultSort('due_on')
            ->recordActions([
                Action::make('upload')->icon(Heroicon::OutlinedArrowUpTray)->color('primary')
                    ->visible(fn (DocumentRequest $record) => $record->status === 'open')
                    ->schema([
                        FileUpload::make('file')->label('Document')->required()
                            ->storeFiles(false)
                            ->maxSize(self::MAX_UPLOAD_KB)
                            ->acceptedFileTypes(DocumentService::ALLOWED_MIME_TYPES)
                            ->helperText('PDF, Word, Excel, images and similar. Up to 20 MB. Files are scanned for malware.'),
                    ])
                    ->action(function (DocumentRequest $record, array $data) {
                        /** @var TemporaryUploadedFile $file */
                        $file = $data['file'];
                        try {
                            $document = app(DocumentService::class)->storeForClient($file, $record, auth()->user());
                        } catch (\InvalidArgumentException $exception) {
                            Notification::make()->title('Upload rejected')->body($exception->getMessage())->danger()->send();

                            return;
                        }

                        if ($document->currentVersion === null) {
                            Notification::make()->title('Upload rejected')->body('The file could not be accepted. Please contact the firm.')->danger()->send();

                            return;
                        }

                        PortalNotifier::staff($record->matter, $record->matter->client, 'Document received from client',
                            ['Matter '.$record->matter->reference, 'Requested document: '.$record->title],
                            StaffMatterResource::getUrl('view', ['record' => $record->matter], panel: 'admin'));

                        Notification::make()->title('Thank you, your document was uploaded')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListDocumentRequests::route('/')];
    }
}
