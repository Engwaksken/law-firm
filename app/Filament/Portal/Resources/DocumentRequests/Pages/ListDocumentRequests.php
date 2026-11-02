<?php

namespace App\Filament\Portal\Resources\DocumentRequests\Pages;

use App\Filament\Portal\Resources\DocumentRequests\DocumentRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListDocumentRequests extends ListRecords
{
    protected static string $resource = DocumentRequestResource::class;
}
