<?php

namespace App\Livewire;

use App\Livewire\Concerns\ProtectsPublicForms;
use App\Models\NewsletterSubscriber;
use App\Notifications\Website\ConfirmNewsletterSubscription;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Validate;
use Livewire\Component;

/** Double opt-in sign-up. The response is identical whether or not the email already exists. */
class NewsletterSignup extends Component
{
    use ProtectsPublicForms;

    public string $variant = 'light';

    #[Validate('required|email:rfc|max:255')]
    public string $email = '';

    public bool $done = false;

    public function subscribe(): void
    {
        $this->validate();

        if ($this->passesSpamChecks('newsletter')) {
            $subscriber = NewsletterSubscriber::firstOrCreate(['email' => mb_strtolower(trim($this->email))]);

            // Re-send confirmation only to people who aren't currently active subscribers.
            if ($subscriber->confirmed_at === null || $subscriber->unsubscribed_at !== null) {
                Notification::route('mail', $subscriber->email)->notify(new ConfirmNewsletterSubscription($subscriber));
            }
        }

        $this->reset('email');
        $this->done = true;
    }

    public function render()
    {
        return view('livewire.newsletter-signup');
    }
}
