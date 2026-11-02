<?php

namespace App\Filament\Resources\CareerApplications\Pages;

use App\Filament\Resources\CareerApplications\CareerApplicationResource;
use App\Models\CareerApplication;
use App\Notifications\Admin\ApplicationStatusUpdated;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Notification;

class EditCareerApplication extends EditRecord
{
    protected static string $resource = CareerApplicationResource::class;

    private ?string $statusBefore = null;

    protected function getHeaderActions(): array
    {
        return [CareerApplicationResource::downloadCvAction()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->statusBefore = $this->record->status;

        // Only status and notes are editable here, whatever was submitted.
        return array_intersect_key($data, array_flip(['status', 'internal_notes'])) + ['reviewed_by' => auth()->id()];
    }

    protected function afterSave(): void
    {
        $status = $this->record->status;
        if ($status !== $this->statusBefore && in_array($status, CareerApplication::NOTIFY_ON, true)) {
            Notification::route('mail', $this->record->email)->notify(new ApplicationStatusUpdated($this->record->fresh('career')));
        }
    }
}
