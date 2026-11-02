<?php

namespace App\Notifications\Admin;

use App\Models\CareerApplication;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Status update to a job applicant. Never includes internal notes. */
class ApplicationStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CareerApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firm = Settings::get('firm_name');
        $a = $this->application;
        $role = $a->career?->title;

        $line = match ($a->status) {
            'under_review' => 'Your application is now being reviewed.',
            'shortlisted' => 'We are pleased to let you know you have been shortlisted.',
            'interview' => 'We would like to invite you to an interview. We will contact you shortly with the details.',
            'offered' => 'We are pleased to inform you that we would like to make you an offer. We will be in touch with the details.',
            'unsuccessful' => 'Thank you for your interest. After careful consideration, we will not be taking your application further on this occasion.',
            default => 'The status of your application is now: '.$a->statusLabel().'.',
        };

        return (new MailMessage)
            ->subject("{$firm}: your application {$a->reference}")
            ->greeting('Dear '.$a->name.',')
            ->line("Application reference: {$a->reference}".($role ? " ({$role})" : ''))
            ->line($line)
            ->salutation('Regards, '.$firm);
    }
}
