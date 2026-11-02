<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    protected static ?string $title = 'Upload media';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [
            ...$data,
            ...MediaResource::metadataFor($data['path']),
            'disk' => MediaResource::DISK,
            'uploaded_by' => auth()->id(),
            'original_name' => $data['original_name'] ?? basename($data['path']),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
