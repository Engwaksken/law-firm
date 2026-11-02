<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make(), RestoreAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // A non-publisher editing an already-published article keeps it published but
        // cannot publish a draft: only downgrade attempts to publish.
        if (! auth()->user()->can('content.publish') && $this->record->status === 'published') {
            $data['status'] = 'published';
            $data['published_at'] = $this->record->published_at;

            return $data;
        }

        return ArticleResource::enforcePublishingRules($data);
    }
}
