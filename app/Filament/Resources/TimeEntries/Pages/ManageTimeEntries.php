<?php

namespace App\Filament\Resources\TimeEntries\Pages;

use App\Filament\Resources\TimeEntries\TimeEntryResource;
use App\Filament\Support\MatterOptions;
use App\Models\TimeEntry;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

class ManageTimeEntries extends ManageRecords
{
    protected static string $resource = TimeEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Record time')
                ->using(function (array $data, array $arguments, $livewire): Model {
                    MatterOptions::authorizeFor(TimeEntry::class, (int) $data['matter_id']);
                    $hours = (float) ($livewire->mountedActions[0]['data']['hours'] ?? 0);
                    // Users without rate-setting rights always get the default rate.
                    if (! auth()->user()->can('invoices.issue') && ! auth()->user()->can('matters.assign')) {
                        $data['rate'] = TimeEntryResource::defaultRate((int) $data['matter_id']) ?? 0;
                    }

                    return TimeEntry::create($data + ['minutes' => (int) round($hours * 60), 'user_id' => auth()->id()]);
                }),
        ];
    }
}
