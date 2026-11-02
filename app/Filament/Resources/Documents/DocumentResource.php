<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Filament\Resources\Documents\RelationManagers\AccessLogsRelationManager;
use App\Filament\Resources\Documents\RelationManagers\VersionsRelationManager;
use App\Filament\Support\MatterOptions;
use App\Legal\DocumentService;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVersion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Legal Practice';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'title';

    /** Lists, search and record binding only ever see documents the user may access. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())->with(['matter', 'currentVersion', 'uploader']);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class])->visibleTo(auth()->user());
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title'];
    }

    /** Upload form, shared with the matter relation manager. */
    public static function uploadFields(bool $withOwner = true): array
    {
        $owner = $withOwner ? [
            Radio::make('owner')->label('Belongs to')->inline()->live()->default('matter')->required()
                ->options(array_filter([
                    'matter' => 'A matter',
                    'client' => auth()->user()?->can('clients.manage') ? 'A client (no matter)' : null,
                    'template' => 'Firm template',
                ])),
            Select::make('matter_id')->label('Matter')->searchable()
                ->options(fn () => MatterOptions::contributable(Document::class))
                ->visible(fn (Get $get) => $get('owner') === 'matter')
                ->required(fn (Get $get) => $get('owner') === 'matter'),
            Select::make('client_id')->label('Client')->searchable()
                ->options(fn () => Client::query()->orderBy('name')->limit(500)->pluck('name', 'id'))
                ->visible(fn (Get $get) => $get('owner') === 'client')
                ->required(fn (Get $get) => $get('owner') === 'client'),
        ] : [];

        return [
            Section::make()->columns(2)->columnSpanFull()->schema([
                ...$owner,
                TextInput::make('title')->required()->maxLength(200),
                Select::make('category')->options(Document::CATEGORIES)->default('other')->required(),
                TextInput::make('folder')->maxLength(80)->datalist(['Pleadings', 'Correspondence', 'Evidence', 'Agreements', 'Court orders', 'Opinions'])
                    ->helperText('Optional folder name within the matter.'),
                Toggle::make('is_evidence')->label('Evidence (chain of custody: cannot be deleted)')->inline(false),
                Textarea::make('description')->rows(2)->columnSpanFull(),
                FileUpload::make('file')->label('File')->required()->storeFiles(false)
                    ->maxSize((int) config('firm.documents.max_upload_kb', 51200))
                    ->acceptedFileTypes(DocumentService::ALLOWED_MIME_TYPES)
                    ->helperText('PDF, Word, Excel, PowerPoint, text, images or email files. Archives and executables are not accepted.')
                    ->columnSpanFull(),
            ]),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::uploadFields());
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('title'),
                TextEntry::make('matter.reference')->label('Matter')->placeholder('—'),
                TextEntry::make('client.name')->label('Client')->placeholder('—'),
                TextEntry::make('category')->formatStateUsing(fn (string $state) => Document::CATEGORIES[$state] ?? $state),
                TextEntry::make('folder')->placeholder('—'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (string $state) => Document::STATUSES[$state] ?? $state),
                TextEntry::make('currentVersion.version_number')->label('Current version')->placeholder('—'),
                TextEntry::make('currentVersion.scan_status')->label('Malware scan')->badge()
                    ->formatStateUsing(fn (?string $state) => DocumentVersion::SCAN_STATUSES[$state] ?? '—')
                    ->color(fn (?string $state) => static::scanColor($state)),
                TextEntry::make('approver.name')->label('Approved by')->placeholder('—'),
                TextEntry::make('is_evidence')->label('Evidence')->formatStateUsing(fn (bool $state) => $state ? 'Yes' : 'No'),
                TextEntry::make('description')->columnSpanFull()->placeholder('—'),
            ]),
        ]);
    }

    public static function scanColor(?string $state): string
    {
        return match ($state) {
            'clean' => 'success',
            'infected', 'error' => 'danger',
            default => 'warning',
        };
    }

    public static function table(Table $table): Table
    {
        return static::tableFor($table, showMatter: true);
    }

    public static function tableFor(Table $table, bool $showMatter): Table
    {
        return $table
            ->columns(array_filter([
                TextColumn::make('title')->searchable()->sortable()->description(fn (Document $record) => $record->folder),
                $showMatter ? TextColumn::make('matter.reference')->label('Matter')->placeholder(fn (Document $record) => $record->is_template ? 'Template' : 'Client file') : null,
                TextColumn::make('category')->badge()->color('gray')->formatStateUsing(fn (string $state) => Document::CATEGORIES[$state] ?? $state),
                TextColumn::make('currentVersion.version_number')->label('Ver.')->prefix('v'),
                TextColumn::make('currentVersion.scan_status')->label('Scan')->badge()
                    ->formatStateUsing(fn (?string $state) => DocumentVersion::SCAN_STATUSES[$state] ?? 'No file')
                    ->color(fn (?string $state) => static::scanColor($state)),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => Document::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'approved' ? 'success' : ($state === 'pending_approval' ? 'info' : 'gray')),
                IconColumn::make('is_evidence')->label('Evidence')->boolean()->toggleable(),
                TextColumn::make('uploader.name')->label('Uploaded by')->toggleable(),
                TextColumn::make('created_at')->label('Uploaded')->since()->sortable(),
            ]))
            ->defaultSort('created_at', 'desc')
            ->filters(array_filter([
                $showMatter ? SelectFilter::make('matter_id')->label('Matter')->options(fn () => MatterOptions::visible())->searchable() : null,
                SelectFilter::make('category')->options(Document::CATEGORIES),
                SelectFilter::make('status')->options(Document::STATUSES),
                SelectFilter::make('scan')->label('Scan status')->options(DocumentVersion::SCAN_STATUSES)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null,
                        fn (Builder $query, $value) => $query->whereHas('currentVersion', fn (Builder $query) => $query->where('scan_status', $value)))),
                TernaryFilter::make('is_evidence')->label('Evidence'),
                TrashedFilter::make(),
            ]))
            ->recordActions([
                Action::make('download')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                    ->visible(fn (Document $record) => $record->currentVersion?->isDownloadable() && auth()->user()->can('download', $record))
                    ->action(fn (Document $record) => redirect()->away(app(DocumentService::class)->temporaryDownloadUrl($record->currentVersion, auth()->user()))),
                ViewAction::make()->url(fn (Document $record) => static::getUrl('view', ['record' => $record])),
            ]);
    }

    public static function getRelations(): array
    {
        return [VersionsRelationManager::class, AccessLogsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'view' => ViewDocument::route('/{record}'),
        ];
    }
}
