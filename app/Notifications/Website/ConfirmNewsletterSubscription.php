<?php

namespace App\Notifications\Website;

use App\Models\NewsletterSubscriber;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ConfirmNewsletterSubscription extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public NewsletterSubscriber $subscriber) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $confirmUrl = URL::temporarySignedRoute('newsletter.confirm', now()->addDays(7), ['subscriber' => $this->subscriber]);

        return (new MailMessage)
            ->subject('Please confirm your subscription | '.Settings::get('firm_name'))
            ->line('Please confirm that you would like to receive newsletters and legal updates from '.Settings::get('firm_name').'.')
            ->action('Confirm subscription', $confirmUrl)
            ->line('If you did not request this, you can ignore this email and you will not be subscribed.');
    }
}
