<?php

namespace App\Livewire;

use App\Livewire\Concerns\LinksToAdmin;
use App\Livewire\Concerns\ProtectsPublicForms;
use App\Models\Advocate;
use App\Models\Consultation;
use App\Models\PracticeArea;
use App\Notifications\Website\NewSubmissionForStaff;
use App\Notifications\Website\SubmissionAcknowledgement;
use App\Support\StaffRecipients;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ConsultationBooking extends Component
{
    use LinksToAdmin, ProtectsPublicForms;

    public ?int $practiceAreaId = null;

    public ?int $advocateId = null;

    public string $consultationType = 'in_person';

    public string $preferredDate = '';

    public string $preferredTime = '';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $message = '';

    public bool $privacy = false;

    #[Locked]
    public ?string $reference = null;

    /** Pre-select from query string slugs (?practice_area=…&advocate=…). */
    public function mount(?string $practiceArea = null, ?string $advocate = null): void
    {
        if ($practiceArea) {
            $this->practiceAreaId = PracticeArea::query()->visible()->where('slug', $practiceArea)->value('id');
        }
        if ($advocate && ($found = Advocate::query()->published()->where('accepts_consultations', true)->where('slug', $advocate)->first())) {
            $this->advocateId = $found->id;
            $this->practiceAreaId ??= $found->practiceAreas()->visible()->value('practice_areas.id');
        }
    }

    #[Computed]
    public function practiceAreas(): Collection
    {
        return PracticeArea::query()->visible()->get(['id', 'name']);
    }

    /** Only published advocates who accept consultations, narrowed to the chosen area. */
    #[Computed]
    public function advocates(): Collection
    {
        return $this->advocateQuery()->get(['advocates.id', 'advocates.name', 'advocates.title']);
    }

    public function updatedPracticeAreaId(): void
    {
        if ($this->advocateId && ! $this->advocateQuery()->whereKey($this->advocateId)->exists()) {
            $this->advocateId = null;
        }
    }

    protected function rules(): array
    {
        return [
            'practiceAreaId' => ['nullable', Rule::exists('practice_areas', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'advocateId' => ['nullable', Rule::in($this->advocateQuery()->pluck('advocates.id')->all())],
            'consultationType' => ['required', Rule::in(array_keys(Consultation::TYPES))],
            'preferredDate' => ['required', 'date', 'after_or_equal:today', 'before:'.now()->addMonths(6)->toDateString()],
            'preferredTime' => ['nullable', Rule::in(['morning', 'afternoon', 'any'])],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:2000'],
            'privacy' => ['accepted'],
        ];
    }

    protected function messages(): array
    {
        return [
            'privacy.accepted' => 'Please confirm you have read the confidentiality and privacy notice.',
            'advocateId.in' => 'Please choose an advocate from the list.',
            'preferredDate.after_or_equal' => 'Please choose today or a later date.',
        ];
    }

    protected function validationAttributes(): array
    {
        return ['practiceAreaId' => 'practice area', 'advocateId' => 'advocate', 'consultationType' => 'consultation type', 'preferredDate' => 'preferred date'];
    }

    public function submit(): void
    {
        $this->validate();

        $reference = 'CON-SUBMITTED';
        if ($this->passesSpamChecks('consultation')) {
            $consultation = Consultation::create([
                'practice_area_id' => $this->practiceAreaId,
                'advocate_id' => $this->advocateId,
                'consultation_type' => $this->consultationType,
                'preferred_date' => $this->preferredDate,
                'preferred_time' => $this->preferredTime ?: null,
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'message' => $this->message ?: null,
                'status' => 'pending',
                'privacy_accepted_at' => now(),
            ]);
            $reference = $consultation->reference;

            StaffRecipients::notify('consultations.manage', new NewSubmissionForStaff(
                'consultation request', $reference,
                $this->adminUrl(\App\Filament\Resources\Consultations\ConsultationResource::class),
            ));
            Notification::route('mail', $this->email)->notify(new SubmissionAcknowledgement(
                $this->name, 'Consultation request received',
                'Thank you for your consultation request. We will contact you shortly to confirm the date, time and format. Your request is not yet a confirmed appointment.',
                $reference,
            ));
        }

        $this->reset(['practiceAreaId', 'advocateId', 'preferredDate', 'preferredTime', 'name', 'email', 'phone', 'message', 'privacy']);
        $this->reference = $reference;
    }

    private function advocateQuery()
    {
        return Advocate::query()->published()->where('accepts_consultations', true)
            ->when($this->practiceAreaId, fn ($q) => $q->whereHas('practiceAreas', fn ($p) => $p->whereKey($this->practiceAreaId)));
    }

    public function render()
    {
        return view('livewire.consultation-booking');
    }
}
