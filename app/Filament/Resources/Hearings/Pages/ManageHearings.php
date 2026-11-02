<?php

namespace App\Filament\Resources\Hearings\Pages;

use App\Filament\Resources\Hearings\HearingResource;
use App\Filament\Support\MatterOptions;
use App\Models\Hearing;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageHearings extends ManageRecords
{
    protected static string $resource = HearingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add hearing')->using(function (array $data) {
                MatterOptions::authorizeFor(Hearing::class, $data['matter_id']);

                return Hearing::create($data);
            }),
        ];
    }
}
