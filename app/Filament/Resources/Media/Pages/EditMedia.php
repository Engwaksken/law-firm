<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

/** Metadata only; the file itself can't be swapped (upload a new item instead). */
class EditMedia extends EditRecord
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make(), RestoreAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['path'], $data['original_name'], $data['disk']);

        return $data;
    }
}
