<?php

namespace App\Notifications\Practice;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;

class DocumentAwaitingApproval extends PracticeNotification
{
    public function __construct(public Document $document) {}

    protected function title(): string { return 'Document awaiting approval'; }

    protected function lines(): array
    {
        return array_values(array_filter([
            'Category: '.(Document::CATEGORIES[$this->document->category] ?? $this->document->category),
            $this->document->matter ? 'Matter: '.$this->document->matter->reference : null,
        ]));
    }

    protected function url(): string { return DocumentResource::getUrl('view', ['record' => $this->document], panel: 'admin'); }

    protected function icon(): string { return 'heroicon-o-document-check'; }
}
