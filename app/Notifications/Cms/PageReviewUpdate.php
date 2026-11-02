<?php

namespace App\Notifications\Cms;

use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PageReviewUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    private const TITLES = [
        'submitted' => 'Page submitted for review',
        'published' => 'Your page was published',
        'changes_requested' => 'Changes requested on your page',
    ];

    public function __construct(
        public Page $page,
        public string $event,
        public string $actorName,
        public ?string $notes = null,
    ) {}

    public function via(object $notifiable): array
    {
        return method_exists($notifiable, 'getKey') ? ['mail', 'database'] : ['mail'];
    }

    private function editUrl(): string
    {
        return PageResource::getUrl('edit', ['record' => $this->page], panel: 'admin');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(self::TITLES[$this->event].': '.$this->page->title)
            ->line(self::TITLES[$this->event].' by '.$this->actorName.': "'.$this->page->title.'".');

        if ($this->notes) {
            $mail->line('Notes: '.$this->notes);
        }

        return $mail->action('Open the page', $this->editUrl());
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(self::TITLES[$this->event])
            ->body($this->page->title.($this->notes ? ' | '.$this->notes : ''))
            ->icon('heroicon-o-document-text')
            ->actions([Action::make('open')->label('Open')->url($this->editUrl())])
            ->getDatabaseMessage();
    }
}
