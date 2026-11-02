<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Legal\DocumentService;
use App\Models\Document;
use App\Models\User;
use App\Notifications\Practice\DocumentAwaitingApproval;
use App\Support\StaffRecipients;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** @property Document $record */
class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        $service = app(DocumentService::class);

        return [
            Action::make('download')->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn () => $this->record->currentVersion?->isDownloadable() && auth()->user()->can('download', $this->record))
                ->action(fn () => redirect()->away($service->temporaryDownloadUrl($this->record->currentVersion, auth()->user()))),

            Action::make('uploadVersion')->label('Upload new version')->icon(Heroicon::OutlinedArrowUpTray)->color('gray')
                ->visible(fn () => auth()->user()->can('uploadVersion', $this->record))
                ->schema([
                    FileUpload::make('file')->required()->storeFiles(false)
                        ->maxSize((int) config('firm.documents.max_upload_kb', 51200))
                        ->acceptedFileTypes(DocumentService::ALLOWED_MIME_TYPES),
                    Textarea::make('notes')->label('What changed?')->rows(2),
                ])
                ->action(function (array $data) use ($service) {
                    $file = is_array($data['file']) ? reset($data['file']) : $data['file'];
                    try {
                        $version = $service->addVersion($this->record, $file, $data['notes'] ?? null, auth()->user());
                    } catch (InvalidArgumentException $e) {
                        throw ValidationException::withMessages(['mountedActions.0.data.file' => $e->getMessage()]);
                    }
                    $this->record->forceFill(['status' => 'draft', 'approved_by' => null, 'approved_at' => null])->save();
                    $infected = $version->scan_status === 'infected';
                    FilamentNotification::make()
                        ->title($infected ? 'Malware detected: the file was quarantined' : 'New version uploaded')
                        ->{$infected ? 'danger' : 'success'}()->send();
                }),

            Action::make('submitForApproval')->label('Submit for approval')->icon(Heroicon::OutlinedPaperAirplane)->color('info')
                ->visible(fn () => $this->record->status === 'draft' && auth()->user()->can('update', $this->record) && $this->record->currentVersion)
                ->requiresConfirmation()
                ->action(function () {
                    Gate::authorize('update', $this->record);
                    $this->record->forceFill(['status' => 'pending_approval'])->save();
                    $approvers = StaffRecipients::withPermission('documents.approve')
                        ->filter(fn (User $u) => $u->id !== auth()->id() && $u->can('approve', $this->record));
                    Notification::send($approvers, new DocumentAwaitingApproval($this->record));
                    FilamentNotification::make()->title('Submitted for approval')->success()->send();
                }),

            Action::make('approve')->icon(Heroicon::OutlinedCheckBadge)->color('success')
                ->visible(fn () => $this->record->status !== 'approved' && auth()->user()->can('approve', $this->record) && $this->record->currentVersion?->isDownloadable())
                ->requiresConfirmation()
                ->action(function () use ($service) {
                    Gate::authorize('approve', $this->record);
                    abort_unless($this->record->currentVersion?->isDownloadable(), 403);
                    $this->record->forceFill(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()])->save();
                    $service->log($this->record, 'approve', $this->record->currentVersion, auth()->user());
                    FilamentNotification::make()->title('Document approved')->success()->send();
                }),

            ActionGroup::make([
                Action::make('delete')->icon(Heroicon::OutlinedTrash)->color('danger')
                    ->visible(fn () => auth()->user()->can('delete', $this->record) && ! $this->record->trashed())
                    ->requiresConfirmation()
                    ->modalDescription('The document is moved to the bin and can be restored. Files are retained.')
                    ->action(function () use ($service) {
                        Gate::authorize('delete', $this->record);
                        $service->log($this->record, 'delete', null, auth()->user());
                        $this->record->delete();
                        $this->redirect(DocumentResource::getUrl('index'));
                    }),
                Action::make('restore')->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn () => $this->record->trashed() && auth()->user()->can('restore', $this->record))
                    ->action(function () use ($service) {
                        Gate::authorize('restore', $this->record);
                        $this->record->restore();
                        $service->log($this->record, 'restore', null, auth()->user());
                    }),
            ]),
        ];
    }
}
