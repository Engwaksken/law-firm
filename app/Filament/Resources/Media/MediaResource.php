<?php

namespace App\Filament\Resources\Media;

use App\Filament\Resources\Media\Pages\CreateMedia;
use App\Filament\Resources\Media\Pages\EditMedia;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Filament\Resources\Media\Schemas\MediaForm;
use App\Filament\Resources\Media\Tables\MediaTable;
use App\Models\Event;
use App\Models\Media;
use App\Models\Page;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Media library';

    protected static ?string $modelLabel = 'media item';

    protected static ?string $pluralModelLabel = 'media library';

    protected static ?string $slug = 'media';

    protected static ?string $recordTitleAttribute = 'original_name';

    public const DISK = 'public';

    public const COLLECTIONS = [
        'default' => 'General', 'branding' => 'Branding', 'practice-areas' => 'Practice areas',
        'advocates' => 'Advocates', 'articles' => 'Articles', 'events' => 'Events', 'pages' => 'Pages',
    ];

    public const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml', 'image/gif'];

    public const DOCUMENT_TYPES = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    public static function form(Schema $schema): Schema
    {
        return MediaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MediaTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
            'create' => CreateMedia::route('/create'),
            'edit' => EditMedia::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function uploadDirectory(): string
    {
        return 'media/'.now()->format('Y/m');
    }

    /**
     * File facts read from the stored file (never trusted from the browser).
     *
     * @return array{mime_type: ?string, size_bytes: ?int, width: ?int, height: ?int}
     */
    public static function metadataFor(string $path): array
    {
        $disk = Storage::disk(self::DISK);
        $mime = $disk->mimeType($path) ?: null;
        $width = $height = null;

        if ($mime && str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml') {
            $size = @getimagesize($disk->path($path));
            if ($size) {
                [$width, $height] = $size;
            }
        }

        return ['mime_type' => $mime, 'size_bytes' => $disk->size($path), 'width' => $width, 'height' => $height];
    }

    /** Whether the (not yet saved) upload state looks like an image; used to require alt text. */
    public static function uploadIsImage(mixed $state): bool
    {
        $file = collect(is_array($state) ? $state : [$state])->filter()->first();

        if ($file instanceof TemporaryUploadedFile) {
            return str_starts_with((string) $file->getMimeType(), 'image/');
        }
        if (is_string($file)) {
            return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'], true);
        }

        return true; // nothing uploaded yet: assume image so the requirement is visible
    }

    /** Places that point at this media item; it can't be erased while any exist. */
    public static function referenceCount(Media $media): int
    {
        return Page::withTrashed()->where('og_image_id', $media->id)->count()
            + Event::withTrashed()->where('image_id', $media->id)->count();
    }

    public static function humanSize(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = $bytes > 0 ? min((int) floor(log($bytes, 1024)), 3) : 0;

        return round($bytes / (1024 ** $i), $i ? 1 : 0).' '.$units[$i];
    }
}
