<?php

namespace App\Filament\Resources\Deadlines\Pages;

use App\Filament\Resources\Deadlines\DeadlineResource;
use App\Filament\Support\MatterOptions;
use App\Models\Deadline;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDeadlines extends ManageRecords
{
    protected static string $resource = DeadlineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add deadline')->using(function (array $data) {
                MatterOptions::authorizeFor(Deadline::class, $data['matter_id']);

                return Deadline::create($data);
            }),
        ];
    }
}
