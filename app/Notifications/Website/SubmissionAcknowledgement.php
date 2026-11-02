<?php

namespace App\Notifications\Website;

use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Confirms receipt to the visitor. Echoes back nothing they wrote except their name. */
class SubmissionAcknowledgement extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $name,
        public string $subject,
        public string $body,
        public ?string $reference = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firm = Settings::get('firm_name');
        $mail = (new MailMessage)
            ->subject($this->subject.' | '.$firm)
            ->greeting('Dear '.$this->name.',')
            ->line($this->body);

        if ($this->reference) {
            $mail->line('Your reference: **'.$this->reference.'**');
        }

        return $mail
            ->line(Settings::get('confidentiality_notice'))
            ->salutation('Kind regards,  '."\n".$firm);
    }
}
