<?php

namespace App\Filament\Portal\Resources\Matters\Pages;

use App\Filament\Portal\Resources\Matters\MatterResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMatter extends ViewRecord
{
    protected static string $resource = MatterResource::class;

    public function getTitle(): string
    {
        return $this->record->reference;
    }
}
