<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Legal\DocumentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return static::storeUpload($data, 'data.file');
    }

    /** Shared with relation managers. $data comes from DocumentResource::uploadFields(). */
    public static function storeUpload(array $data, string $errorKey = 'file', array $overrides = []): Model
    {
        $owner = $overrides ? null : ($data['owner'] ?? 'matter');
        $attributes = [
            'matter_id' => $owner === 'matter' ? ($data['matter_id'] ?? null) : null,
            'client_id' => $owner === 'client' ? ($data['client_id'] ?? null) : null,
            'is_template' => $owner === 'template',
            'title' => $data['title'],
            'category' => $data['category'] ?? 'other',
            'folder' => $data['folder'] ?? null,
            'description' => $data['description'] ?? null,
            'is_evidence' => (bool) ($data['is_evidence'] ?? false),
        ];
        $attributes = array_merge($attributes, $overrides);

        $file = $data['file'] ?? null;
        $file = is_array($file) ? reset($file) : $file;

        try {
            return app(DocumentService::class)->store($file, $attributes, auth()->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([$errorKey => $e->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
