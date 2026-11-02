<?php

namespace App\Notifications\Website;

use App\Support\Settings;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells staff a website form was submitted. Deliberately contains only the
 * kind of submission, a reference and a link to the admin panel. Never the
 * visitor's message, which may contain confidential facts (spec §14).
 */
class NewSubmissionForStaff extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $kind,          // e.g. "consultation request"
        public string $reference,
        public string $adminUrl,
    ) {}

    public function via(object $notifiable): array
    {
        // Anonymous (route) notifiables can't receive database notifications.
        return method_exists($notifiable, 'getKey') ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New '.$this->kind.' ('.$this->reference.')')
            ->line('A new '.$this->kind.' was submitted through the '.Settings::get('firm_name').' website.')
            ->line('Reference: '.$this->reference)
            ->action('Open in the admin panel', $this->adminUrl)
            ->line('For confidentiality, details are only available after signing in.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('New '.$this->kind)
            ->body($this->reference)
            ->icon('heroicon-o-inbox-arrow-down')
            ->actions([\Filament\Actions\Action::make('open')->label('Open')->url($this->adminUrl)])
            ->getDatabaseMessage();
    }
}
