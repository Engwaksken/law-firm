<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Upload')->using(function (array $data) {
            return \App\Models\Media::create([
                ...$data,
                ...MediaResource::metadataFor($data['path']),
                'disk' => MediaResource::DISK,
                'uploaded_by' => auth()->id(),
                'original_name' => $data['original_name'] ?? basename($data['path']),
            ]);
        })];
    }
}
