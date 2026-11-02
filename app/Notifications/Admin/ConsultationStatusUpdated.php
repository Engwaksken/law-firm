<?php

namespace App\Notifications\Admin;

use App\Models\Consultation;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a requester their consultation was confirmed or declined.
 * Deliberately excludes the visitor's message and internal notes.
 */
class ConsultationStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Consultation $consultation, public ?string $reason = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firm = Settings::get('firm_name');
        $c = $this->consultation;
        $mail = (new MailMessage)->greeting('Dear '.$c->name.',');

        if ($c->status === 'confirmed') {
            $mail->subject("{$firm}: consultation confirmed ({$c->reference})")
                ->line("Your consultation request {$c->reference} has been confirmed.")
                ->line('Date and time: '.$c->scheduled_at?->timezone(Settings::get('timezone'))->format('l j F Y, H:i'))
                ->line('Format: '.(Consultation::TYPES[$c->consultation_type] ?? $c->consultation_type))
                ->when($c->advocate, fn ($m) => $m->line('Advocate: '.$c->advocate->name));
        } else {
            $mail->subject("{$firm}: update on your consultation request ({$c->reference})")
                ->line("We are unable to accept your consultation request {$c->reference} at this time.")
                ->when(filled($this->reason), fn ($m) => $m->line('Reason: '.$this->reason));
        }

        return $mail
            ->line('This message does not create an advocate–client relationship.')
            ->salutation('Regards, '.$firm);
    }
}
