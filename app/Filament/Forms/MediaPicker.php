<?php

namespace App\Filament\Forms;

use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Media\Schemas\MediaForm;
use App\Models\Media;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * Select an image from the media library (stores the Media id), or upload a
 * new one inline. Usage: MediaPicker::make('image_id', 'Image').
 */
class MediaPicker
{
    public static function make(string $name, string $label = 'Image'): Select
    {
        return Select::make($name)
            ->label($label)
            ->searchable()
            ->preload()
            ->options(fn () => self::images()->limit(25)->get()->mapWithKeys(fn (Media $media) => [$media->id => $media->label()])->all())
            ->getSearchResultsUsing(fn (string $search) => self::images()
                ->where(fn ($query) => $query->where('title', 'like', "%{$search}%")
                    ->orWhere('original_name', 'like', "%{$search}%")
                    ->orWhere('alt_text', 'like', "%{$search}%"))
                ->limit(50)->get()
                ->mapWithKeys(fn (Media $media) => [$media->id => $media->label()])->all())
            ->getOptionLabelUsing(fn ($value) => Media::find($value)?->label())
            ->rules([fn () => function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value) && ! self::images()->whereKey($value)->exists()) {
                    $fail('Please choose an image from the media library.');
                }
            }])
            ->createOptionForm([
                MediaForm::uploadField()
                    ->acceptedFileTypes(MediaResource::IMAGE_TYPES)
                    ->helperText('JPG, PNG, WebP, SVG or GIF, up to 10 MB. The image is added to the media library.'),
                MediaForm::altTextField(),
                TextInput::make('title')->maxLength(255),
            ])
            ->createOptionUsing(function (array $data): int {
                return Media::create([
                    ...MediaResource::metadataFor($data['path']),
                    'disk' => MediaResource::DISK,
                    'path' => $data['path'],
                    'original_name' => $data['original_name'] ?? basename($data['path']),
                    'title' => $data['title'] ?? null,
                    'alt_text' => $data['alt_text'] ?? null,
                    'collection' => 'default',
                    'uploaded_by' => auth()->id(),
                ])->getKey();
            })
            ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) $state : null);
    }

    private static function images()
    {
        return Media::query()->where('mime_type', 'like', 'image/%')->latest();
    }
}
