<?php

namespace App\Filament\Portal\Resources\Receipts\Pages;

use App\Filament\Portal\Resources\Receipts\ReceiptResource;
use Filament\Resources\Pages\ListRecords;

class ListReceipts extends ListRecords
{
    protected static string $resource = ReceiptResource::class;
}
