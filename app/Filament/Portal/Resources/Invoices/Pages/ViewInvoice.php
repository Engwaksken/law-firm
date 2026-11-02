<?php

namespace App\Filament\Portal\Resources\Invoices\Pages;

use App\Filament\Portal\Resources\Invoices\InvoiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return 'Invoice '.$this->record->number;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')->label('Download PDF')->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn () => InvoiceResource::pdfAvailable())
                ->url(fn () => route('filament.portal.files.invoice', $this->record)),
        ];
    }
}
