<?php

namespace App\Filament\Resources\Matters\Pages;

use App\Filament\Resources\Matters\MatterResource;
use App\Legal\MatterLifecycle;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMatter extends CreateRecord
{
    protected static string $resource = MatterResource::class;

    protected static ?string $title = 'Open a matter';

    protected function handleRecordCreation(array $data): Model
    {
        if (! auth()->user()->can('matters.assign')) {
            unset($data['is_restricted']);
        }

        return app(MatterLifecycle::class)->open($data, auth()->user());
    }

    protected function getRedirectUrl(): string
    {
        return MatterResource::getUrl('view', ['record' => $this->record]);
    }
}
