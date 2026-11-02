<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Support\MatterOptions;
use App\Models\Appointment;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAppointments extends ManageRecords
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add appointment')->using(function (array $data) {
                MatterOptions::authorizeFor(Appointment::class, $data['matter_id'] ?? null);

                return Appointment::create($data + ['organizer_id' => auth()->id(), 'status' => 'scheduled']);
            }),
        ];
    }
}
