<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Cms\PageWorkflow;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** @property Page $record */
class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['content_blocks'] = $this->record->builderState();

        return $data;
    }

    /** Saving edits the working copy only. Status and publish date change via workflow actions. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $blocks = $data['content_blocks'] ?? [];
        unset($data['content_blocks'], $data['status'], $data['published_at']);

        DB::transaction(function () use ($record, $data, $blocks) {
            $record->update($data);
            $record->syncBlocks($blocks);
        });

        return $record;
    }

    protected function getHeaderActions(): array
    {
        $workflow = app(PageWorkflow::class);

        return [
            Action::make('preview')
                ->icon(Heroicon::OutlinedEye)->color('gray')
                ->url(fn () => route('pages.preview', $this->record), shouldOpenInNewTab: true),

            Action::make('submit')
                ->label('Submit for review')->icon(Heroicon::OutlinedPaperAirplane)->color('info')
                ->visible(fn () => ! auth()->user()->can('publish', $this->record)
                    && in_array($this->record->status, ['draft', 'published'], true)
                    && ! $this->record->isAwaitingReview())
                ->requiresConfirmation()
                ->modalDescription('Save your changes first. Publishers will be notified.')
                ->action(function () use ($workflow) {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $workflow->submit($this->record, auth()->user());
                    Notification::make()->title('Submitted for review')->success()->send();
                    $this->refreshFormData(['status_display']);
                }),

            Action::make('publish')
                ->label(fn () => $this->record->isPublished() ? 'Publish changes' : 'Publish')
                ->icon(Heroicon::OutlinedGlobeAlt)->color('success')
                ->visible(fn () => auth()->user()->can('publish', $this->record) && $this->record->status !== 'archived')
                ->schema([
                    DateTimePicker::make('published_at')->label('Publish date')->seconds(false)
                        ->default(fn () => $this->record->published_at ?? now())
                        ->helperText('A future date schedules the page. The page stays hidden until then.'),
                ])
                ->modalDescription('Your current form changes are saved first, then the page goes live.')
                ->action(function (array $data) use ($workflow) {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $at = filled($data['published_at'] ?? null) ? Carbon::parse($data['published_at']) : null;
                    $workflow->publish($this->record->refresh(), auth()->user(), $at);
                    Notification::make()->title($this->record->isScheduled() ? 'Page scheduled' : 'Page published')->success()->send();
                    $this->refreshFormData(['status_display', 'published_at']);
                }),

            Action::make('requestChanges')
                ->label('Request changes')->icon(Heroicon::OutlinedArrowUturnLeft)->color('warning')
                ->visible(fn () => auth()->user()->can('publish', $this->record) && $this->record->isAwaitingReview())
                ->schema([Textarea::make('notes')->required()->rows(4)->label('What needs to change?')])
                ->action(function (array $data) use ($workflow) {
                    $workflow->requestChanges($this->record, auth()->user(), $data['notes']);
                    Notification::make()->title('Changes requested')->success()->send();
                    $this->refreshFormData(['status_display']);
                }),

            ActionGroup::make([
                Action::make('unpublish')->icon(Heroicon::OutlinedEyeSlash)->color('warning')
                    ->visible(fn () => auth()->user()->can('publish', $this->record) && $this->record->status === 'published')
                    ->requiresConfirmation()->modalDescription('The page will no longer be visible to visitors.')
                    ->action(function () use ($workflow) {
                        $workflow->unpublish($this->record, auth()->user());
                        Notification::make()->title('Page unpublished')->success()->send();
                        $this->refreshFormData(['status_display']);
                    }),
                Action::make('archive')->icon(Heroicon::OutlinedArchiveBox)->color('gray')
                    ->visible(fn () => auth()->user()->can('publish', $this->record) && $this->record->status !== 'archived')
                    ->requiresConfirmation()
                    ->action(function () use ($workflow) {
                        $workflow->archive($this->record, auth()->user());
                        Notification::make()->title('Page archived')->success()->send();
                        $this->refreshFormData(['status_display']);
                    }),
                DeleteAction::make(),
            ]),
        ];
    }
}
