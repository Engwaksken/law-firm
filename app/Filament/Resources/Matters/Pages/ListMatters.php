<?php

namespace App\Filament\Resources\Matters\Pages;

use App\Filament\Resources\Matters\MatterResource;
use App\Legal\MatterLifecycle;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMatters extends ListRecords
{
    protected static string $resource = MatterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Open a matter')->using(function (array $data) {
                if (! auth()->user()->can('matters.assign')) {
                    unset($data['is_restricted']);
                }

                return app(MatterLifecycle::class)->open($data, auth()->user());
            }),
        ];
    }
}
