<?php

namespace App\Filament\Resources\Matters\Pages;

use App\Filament\Resources\ConflictChecks\ConflictCheckResource;
use App\Filament\Resources\Matters\MatterResource;
use App\Legal\MatterLifecycle;
use App\Models\Matter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/** @property Matter $record */
class ViewMatter extends ViewRecord
{
    protected static string $resource = MatterResource::class;

    public function getTitle(): string
    {
        return $this->record->reference;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changeStatus')
                ->label('Change status')->icon(Heroicon::OutlinedArrowsRightLeft)
                ->visible(fn () => auth()->user()->can('changeStatus', $this->record) && MatterLifecycle::nextStatuses($this->record) !== [])
                ->schema([
                    Select::make('status')->label('New status')->required()->live()
                        ->options(fn () => MatterLifecycle::nextStatuses($this->record)),
                    Textarea::make('reason')->rows(3)
                        ->required(fn (Get $get) => filled($get('status')) && MatterLifecycle::reasonRequired($get('status')))
                        ->helperText('Recorded in the status history with your name and the time.'),
                ])
                ->action(function (array $data) {
                    app(MatterLifecycle::class)->transition($this->record, $data['status'], auth()->user(), $data['reason'] ?? null);
                    Notification::make()->title('Status changed to '.$this->record->statusLabel())->success()->send();
                    $this->refreshFormData(['status']);
                }),

            Action::make('conflictCheck')
                ->label('Run conflict check')->icon(Heroicon::OutlinedShieldExclamation)->color('warning')
                ->visible(fn () => auth()->user()->can('conflicts.run') && in_array($this->record->status, ['intake', 'conflict_review'], true))
                ->url(fn () => ConflictCheckResource::getUrl('create', ['matter' => $this->record->id])),

            EditAction::make(),
        ];
    }
}
