<?php

namespace App\Filament\Portal\Resources\Invoices\Pages;

use App\Filament\Portal\Resources\Invoices\InvoiceResource;
use App\Support\PortalAccess;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        $clients = auth()->user()->portalClients()->get();

        return $clients->map(fn ($client) => Action::make('statement-'.$client->id)
            ->label($clients->count() > 1 ? "Statement: {$client->name}" : 'Download statement')
            ->icon(Heroicon::OutlinedDocumentArrowDown)->color('gray')
            ->visible(fn () => InvoiceResource::pdfAvailable() && PortalAccess::hasClient(auth()->user(), $client->id))
            ->url(route('filament.portal.files.statement', $client)))->all();
    }
}
