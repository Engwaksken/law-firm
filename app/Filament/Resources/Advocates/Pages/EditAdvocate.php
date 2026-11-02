<?php

namespace App\Filament\Resources\Advocates\Pages;

use App\Filament\Resources\Advocates\AdvocateResource;
use App\Filament\Resources\Advocates\Tables\AdvocatesTable;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAdvocate extends EditRecord
{
    protected static string $resource = AdvocateResource::class;

    /** @var array<string, mixed> */
    private array $credentialsBefore = [];

    protected function getHeaderActions(): array
    {
        return [
            AdvocatesTable::verifyAction(),
            AdvocatesTable::revokeAction(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $this->credentialsBefore = $this->snapshot();
    }

    /** Verification attests to specific credentials: any change to them must be re-verified. */
    protected function afterSave(): void
    {
        if ($this->record->isVerified() && $this->snapshot() !== $this->credentialsBefore) {
            $this->record->revokeVerification();
            Notification::make()
                ->title('Verification cleared')
                ->body('Credentials changed, so the profile is hidden from the website until re-verified.')
                ->warning()
                ->send();
        }
    }

    private function snapshot(): array
    {
        $this->record->refresh();

        return collect(AdvocateResource::CREDENTIAL_FIELDS)
            ->mapWithKeys(fn ($f) => [$f => json_encode($this->record->getAttribute($f))])
            ->all();
    }
}
