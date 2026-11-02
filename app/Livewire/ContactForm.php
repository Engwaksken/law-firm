<?php

namespace App\Livewire;

use App\Livewire\Concerns\LinksToAdmin;
use App\Livewire\Concerns\ProtectsPublicForms;
use App\Models\ContactInquiry;
use App\Notifications\Website\NewSubmissionForStaff;
use App\Notifications\Website\SubmissionAcknowledgement;
use App\Support\StaffRecipients;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ContactForm extends Component
{
    use LinksToAdmin, ProtectsPublicForms;

    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|email:rfc|max:255')]
    public string $email = '';

    #[Validate('nullable|string|max:40')]
    public string $phone = '';

    #[Validate('nullable|string|max:160')]
    public string $subject = '';

    #[Validate('required|string|min:10|max:5000')]
    public string $message = '';

    #[Validate('accepted', message: 'Please confirm you have read the confidentiality and privacy notice.')]
    public bool $privacy = false;

    public bool $sent = false;

    public function submit(): void
    {
        $this->validate();

        if ($this->passesSpamChecks('contact')) {
            $inquiry = ContactInquiry::create([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone ?: null,
                'subject' => $this->subject ?: null,
                'message' => $this->message,
                'status' => 'new',
                'privacy_accepted_at' => now(),
            ]);

            $reference = 'MSG-'.str_pad((string) $inquiry->id, 6, '0', STR_PAD_LEFT);

            StaffRecipients::notify('inquiries.manage', new NewSubmissionForStaff(
                'website message', $reference,
                $this->adminUrl(\App\Filament\Resources\ContactInquiries\ContactInquiryResource::class),
            ));
            Notification::route('mail', $this->email)->notify(new SubmissionAcknowledgement(
                $this->name, 'We have received your message',
                'Thank you for contacting us. A member of our team will reply as soon as possible.',
            ));
        }

        // Bots get the same success screen so they learn nothing.
        $this->reset(['name', 'email', 'phone', 'subject', 'message', 'privacy']);
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.contact-form');
    }
}
