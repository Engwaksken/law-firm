<?php

namespace App\Livewire;

use App\Livewire\Concerns\LinksToAdmin;
use App\Livewire\Concerns\ProtectsPublicForms;
use App\Models\Career;
use App\Models\CareerApplication;
use App\Notifications\Website\NewSubmissionForStaff;
use App\Notifications\Website\SubmissionAcknowledgement;
use App\Support\StaffRecipients;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class CareerApplicationForm extends Component
{
    use LinksToAdmin, ProtectsPublicForms, WithFileUploads;

    #[Locked]
    public Career $career;

    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|email:rfc|max:255')]
    public string $email = '';

    #[Validate('nullable|string|max:40')]
    public string $phone = '';

    #[Validate('nullable|string|max:5000')]
    public string $coverLetter = '';

    /** PDF or Word only, max 5 MB. The MIME type is checked from content, not the extension. */
    #[Validate('required|file|max:5120|mimes:pdf,doc,docx|mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document')]
    public $cv;

    #[Validate('accepted', message: 'Please confirm you have read the privacy notice.')]
    public bool $privacy = false;

    #[Locked]
    public ?string $reference = null;

    public function submit(): void
    {
        abort_unless($this->career->isOpen(), 403, 'This position is closed.');

        $this->validate();

        $reference = 'APP-SUBMITTED';
        if ($this->passesSpamChecks('career-application', maxAttempts: 3)) {
            // Random file name on the PRIVATE disk; the original name is kept only as metadata.
            $path = $this->cv->storeAs('applications/'.now()->format('Y/m'), Str::uuid().'.'.$this->cv->extension(), CareerApplication::CV_DISK);

            $application = CareerApplication::create([
                'career_id' => $this->career->id,
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone ?: null,
                'cover_letter' => $this->coverLetter ?: null,
                'cv_path' => $path,
                'cv_original_name' => Str::limit(basename($this->cv->getClientOriginalName()), 200, ''),
                'status' => 'received',
                'privacy_accepted_at' => now(),
            ]);
            $reference = $application->reference;

            StaffRecipients::notify('careers.manage', new NewSubmissionForStaff(
                'job application for '.$this->career->title, $reference,
                $this->adminUrl(\App\Filament\Resources\CareerApplications\CareerApplicationResource::class),
            ));
            Notification::route('mail', $this->email)->notify(new SubmissionAcknowledgement(
                $this->name, 'Application received: '.$this->career->title,
                'Thank you for applying for the position of '.$this->career->title.'. We will review your application and contact you about the next steps.',
                $reference,
            ));
        }

        $this->reset(['name', 'email', 'phone', 'coverLetter', 'cv', 'privacy']);
        $this->reference = $reference;
    }

    public function render()
    {
        return view('livewire.career-application-form');
    }
}
