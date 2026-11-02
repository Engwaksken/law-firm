<?php

namespace App\Notifications\Practice;

use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for legal-practice notifications. Email carries only minimal,
 * non-confidential facts (type, date, matter reference) plus a link to sign
 * in; details stay inside the system (spec §14).
 */
abstract class PracticeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract protected function title(): string;

    /** @return list<string> short, non-confidential lines */
    abstract protected function lines(): array;

    abstract protected function url(): string;

    protected function icon(): string
    {
        return 'heroicon-o-bell';
    }

    public function via(object $notifiable): array
    {
        return method_exists($notifiable, 'getKey') ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title().' | '.Settings::get('firm_name'));
        foreach ($this->lines() as $line) {
            $mail->line($line);
        }

        return $mail->action('Open in the system', $this->url())
            ->line('Details are available after signing in.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title())
            ->body(implode(' · ', $this->lines()))
            ->icon($this->icon())
            ->actions([Action::make('open')->label('Open')->url($this->url())])
            ->getDatabaseMessage();
    }
}
