<?php

namespace App\Notifications\Portal;

use App\Models\User;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification to a CLIENT portal user. Email carries no confidential content
 * (only that something is waiting and a sign-in link) and is sent only when
 * the user's preference for the topic allows it; the in-portal (database)
 * notification is always stored.
 */
abstract class PortalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Preference key: messages | updates | invoices | appointments | document_requests */
    abstract protected function topic(): string;

    abstract protected function title(): string;

    abstract protected function line(): string;

    abstract protected function url(): string;

    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        return $notifiable->wantsNotification($this->topic()) ? ['mail', 'database'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title().' | '.Settings::get('firm_name'))
            ->line($this->line())
            ->action('Sign in to the client portal', $this->url())
            ->line('For your security, details are only available after signing in.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title())
            ->body($this->line())
            ->actions([Action::make('open')->label('Open')->url($this->url())])
            ->getDatabaseMessage();
    }
}
