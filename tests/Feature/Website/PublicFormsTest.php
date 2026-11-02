<?php

namespace Tests\Feature\Website;

use App\Livewire\CareerApplicationForm;
use App\Livewire\ConsultationBooking;
use App\Livewire\ContactForm;
use App\Livewire\NewsletterSignup;
use App\Models\Advocate;
use App\Models\Career;
use App\Models\CareerApplication;
use App\Models\Consultation;
use App\Models\ContactInquiry;
use App\Models\NewsletterSubscriber;
use App\Models\PracticeArea;
use App\Notifications\Website\ConfirmNewsletterSubscription;
use App\Notifications\Website\NewSubmissionForStaff;
use App\Notifications\Website\SubmissionAcknowledgement;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class PublicFormsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedAccess();
        Notification::fake();
        RateLimiter::clear('public-form:contact:127.0.0.1');
    }

    public function test_contact_form_stores_inquiry_and_notifies_staff_without_the_message(): void
    {
        $receptionist = $this->userWithRole(Access::RECEPTIONIST);
        $editorWithoutAccess = $this->userWithRole(Access::FINANCE_OFFICER);

        Livewire::test(ContactForm::class)
            ->set('name', 'Amina N.')
            ->set('email', 'amina@example.test')
            ->set('message', 'Confidential: my landlord is evicting me unlawfully.')
            ->set('privacy', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        $inquiry = ContactInquiry::sole();
        $this->assertSame('new', $inquiry->status);
        $this->assertNotNull($inquiry->privacy_accepted_at);

        Notification::assertSentTo($receptionist, NewSubmissionForStaff::class, function ($n) use ($receptionist) {
            $mail = $n->toMail($receptionist);

            return ! str_contains(implode(' ', $mail->introLines), 'evicting');
        });
        Notification::assertNotSentTo($editorWithoutAccess, NewSubmissionForStaff::class);
        Notification::assertSentTo(new AnonymousNotifiable, SubmissionAcknowledgement::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'amina@example.test');
    }

    public function test_contact_form_validates_and_requires_privacy_acknowledgement(): void
    {
        Livewire::test(ContactForm::class)
            ->set('email', 'not-an-email')
            ->call('submit')
            ->assertHasErrors(['name' => 'required', 'email', 'message' => 'required', 'privacy' => 'accepted']);

        $this->assertSame(0, ContactInquiry::count());
    }

    public function test_honeypot_submissions_are_discarded_silently(): void
    {
        Livewire::test(ContactForm::class)
            ->set('name', 'Bot')->set('email', 'bot@example.test')
            ->set('message', 'Buy cheap things now!!!')->set('privacy', true)
            ->set('website', 'http://spam.example')
            ->call('submit')
            ->assertSet('sent', true);

        $this->assertSame(0, ContactInquiry::count());
        Notification::assertNothingSent();
    }

    public function test_contact_form_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Livewire::test(ContactForm::class)
                ->set('name', 'Person')->set('email', "p{$i}@example.test")
                ->set('message', 'A legitimate question about services.')->set('privacy', true)
                ->call('submit')->assertHasNoErrors();
        }

        Livewire::test(ContactForm::class)
            ->set('name', 'Person')->set('email', 'p6@example.test')
            ->set('message', 'A legitimate question about services.')->set('privacy', true)
            ->call('submit')
            ->assertHasErrors('form');

        $this->assertSame(5, ContactInquiry::count());
    }

    public function test_consultation_request_creates_reference_and_notifies(): void
    {
        $area = PracticeArea::factory()->create();
        $advocate = Advocate::factory()->published()->acceptingConsultations()->create();
        $advocate->practiceAreas()->attach($area);
        $manager = $this->userWithRole(Access::RECEPTIONIST);

        $component = Livewire::test(ConsultationBooking::class)
            ->set('practiceAreaId', $area->id)
            ->set('advocateId', $advocate->id)
            ->set('consultationType', 'video')
            ->set('preferredDate', now()->addDays(3)->toDateString())
            ->set('name', 'Brian K.')->set('email', 'brian@example.test')->set('phone', '+256700000000')
            ->set('privacy', true)
            ->call('submit')
            ->assertHasNoErrors();

        $consultation = Consultation::sole();
        $this->assertMatchesRegularExpression('/^CON-\d{4}-[A-Z0-9]{6}$/', $consultation->reference);
        $component->assertSet('reference', $consultation->reference);
        $this->assertSame($advocate->id, $consultation->advocate_id);
        $this->assertSame('video', $consultation->consultation_type);
        Notification::assertSentTo($manager, NewSubmissionForStaff::class);
    }

    public function test_consultation_rejects_unpublished_or_unavailable_advocates_and_past_dates(): void
    {
        $hidden = Advocate::factory()->acceptingConsultations()->create(); // not verified/public
        $notAccepting = Advocate::factory()->published()->create();

        foreach ([$hidden, $notAccepting] as $advocate) {
            Livewire::test(ConsultationBooking::class)
                ->set('advocateId', $advocate->id)
                ->set('preferredDate', now()->subDay()->toDateString())
                ->set('name', 'X')->set('email', 'x@example.test')->set('phone', '1')->set('privacy', true)
                ->call('submit')
                ->assertHasErrors(['advocateId', 'preferredDate']);
        }

        $this->assertSame(0, Consultation::count());
    }

    public function test_consultation_preselects_from_query_parameters(): void
    {
        $area = PracticeArea::factory()->create();
        $advocate = Advocate::factory()->published()->acceptingConsultations()->create();
        $advocate->practiceAreas()->attach($area);

        Livewire::test(ConsultationBooking::class, ['advocate' => $advocate->slug])
            ->assertSet('advocateId', $advocate->id)
            ->assertSet('practiceAreaId', $area->id);
    }

    public function test_career_application_stores_cv_privately_and_notifies(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $career = Career::factory()->open()->create();
        $hr = $this->userWithRole(Access::MANAGING_PARTNER);

        Livewire::test(CareerApplicationForm::class, ['career' => $career])
            ->set('name', 'Grace A.')->set('email', 'grace@example.test')
            ->set('cv', UploadedFile::fake()->create('My CV.pdf', 200, 'application/pdf'))
            ->set('privacy', true)
            ->call('submit')
            ->assertHasNoErrors();

        $application = CareerApplication::sole();
        Storage::disk('local')->assertExists($application->cv_path);
        $this->assertSame([], Storage::disk('public')->allFiles(), 'CVs must never land on the public disk');
        $this->assertStringNotContainsString('My CV', $application->cv_path, 'Stored file name must be random');
        $this->assertSame('My CV.pdf', $application->cv_original_name);
        Notification::assertSentTo($hr, NewSubmissionForStaff::class);
    }

    public function test_career_application_rejects_disallowed_files(): void
    {
        Storage::fake('local');
        $career = Career::factory()->open()->create();

        Livewire::test(CareerApplicationForm::class, ['career' => $career])
            ->set('name', 'Grace A.')->set('email', 'grace@example.test')
            ->set('cv', UploadedFile::fake()->create('payload.exe', 10, 'application/x-msdownload'))
            ->set('privacy', true)
            ->call('submit')
            ->assertHasErrors('cv');

        Livewire::test(CareerApplicationForm::class, ['career' => $career])
            ->set('name', 'Grace A.')->set('email', 'grace@example.test')
            ->set('cv', UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf'))
            ->set('privacy', true)
            ->call('submit')
            ->assertHasErrors('cv');

        $this->assertSame(0, CareerApplication::count());
    }

    public function test_closed_vacancy_rejects_applications(): void
    {
        Storage::fake('local');
        $career = Career::factory()->closed()->create();

        Livewire::test(CareerApplicationForm::class, ['career' => $career])
            ->set('name', 'Late')->set('email', 'late@example.test')
            ->set('cv', UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'))
            ->set('privacy', true)
            ->call('submit')
            ->assertForbidden();

        $this->assertSame(0, CareerApplication::count());
    }

    public function test_newsletter_uses_double_opt_in_with_signed_links(): void
    {
        Livewire::test(NewsletterSignup::class)->set('email', 'Reader@Example.test')->call('subscribe')->assertSet('done', true);

        $subscriber = NewsletterSubscriber::sole();
        $this->assertSame('reader@example.test', $subscriber->email);
        $this->assertNull($subscriber->confirmed_at);
        Notification::assertSentTo(new AnonymousNotifiable, ConfirmNewsletterSubscription::class);

        // Tampered/unsigned link is rejected.
        $this->get(route('newsletter.confirm', $subscriber))->assertForbidden();
        $this->assertNull($subscriber->fresh()->confirmed_at);

        $this->get(URL::temporarySignedRoute('newsletter.confirm', now()->addDay(), ['subscriber' => $subscriber]))->assertOk();
        $this->assertNotNull($subscriber->fresh()->confirmed_at);

        $this->get(URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $subscriber]))->assertOk();
        $this->assertNotNull($subscriber->fresh()->unsubscribed_at);
    }

    public function test_newsletter_does_not_reveal_or_resend_for_existing_active_subscribers(): void
    {
        NewsletterSubscriber::factory()->confirmed()->create(['email' => 'known@example.test']);

        Livewire::test(NewsletterSignup::class)->set('email', 'known@example.test')->call('subscribe')->assertSet('done', true);

        Notification::assertNothingSent();
        $this->assertSame(1, NewsletterSubscriber::count());
    }

    public function test_submission_falls_back_to_firm_email_when_no_staff_hold_the_permission(): void
    {
        \App\Support\Settings::setMany(['contact_email' => 'office@firm.test']);

        Livewire::test(ContactForm::class)
            ->set('name', 'Person')->set('email', 'person@example.test')
            ->set('message', 'Question about office hours.')->set('privacy', true)
            ->call('submit');

        Notification::assertSentTo(new AnonymousNotifiable, NewSubmissionForStaff::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'office@firm.test');
    }

    public function test_visitor_message_is_not_written_to_the_audit_log(): void
    {
        Livewire::test(ContactForm::class)
            ->set('name', 'Person')->set('email', 'person@example.test')
            ->set('message', 'Secret facts about my case.')->set('privacy', true)
            ->call('submit');

        $this->assertStringNotContainsString('Secret facts', \App\Models\ActivityLog::pluck('properties')->toJson());
    }
}
