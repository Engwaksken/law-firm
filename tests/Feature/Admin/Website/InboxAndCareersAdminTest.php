<?php

namespace Tests\Feature\Admin\Website;

use App\Filament\Resources\CareerApplications\Pages\EditCareerApplication;
use App\Filament\Resources\CareerApplications\Pages\ListCareerApplications;
use App\Filament\Resources\ContactInquiries\Pages\ListContactInquiries;
use App\Filament\Resources\Consultations\Pages\ListConsultations;
use App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use App\Models\CareerApplication;
use App\Models\Consultation;
use App\Models\ContactInquiry;
use App\Models\NewsletterSubscriber;
use App\Notifications\Admin\ApplicationStatusUpdated;
use App\Notifications\Admin\ConsultationStatusUpdated;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InboxAndCareersAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->withoutMfaEnforcement();
    }

    public function test_receptionist_confirms_consultation_and_requester_is_notified_without_their_message(): void
    {
        Notification::fake();
        $this->actingAs($this->userWithRole(Access::RECEPTIONIST));
        $consultation = Consultation::factory()->create([
            'email' => 'requester@example.test',
            'message' => 'CONFIDENTIAL-FACTS-about my dispute',
        ]);

        Livewire::test(ListConsultations::class)
            ->callTableAction('confirm', $consultation, data: ['scheduled_at' => now()->addDays(3)->setTime(10, 0)->toDateTimeString()])
            ->assertHasNoTableActionErrors();

        $this->assertSame('confirmed', $consultation->fresh()->status);
        $this->assertNotNull($consultation->fresh()->scheduled_at);

        Notification::assertSentTo(new AnonymousNotifiable, ConsultationStatusUpdated::class,
            function (ConsultationStatusUpdated $n, array $channels, AnonymousNotifiable $notifiable) {
                $mail = $n->toMail($notifiable);
                $text = implode(' ', array_merge([$mail->subject], $mail->introLines, $mail->outroLines));

                return $notifiable->routes['mail'] === 'requester@example.test'
                    && str_contains($text, $n->consultation->reference)
                    && ! str_contains($text, 'CONFIDENTIAL-FACTS');
            });
    }

    public function test_declining_a_consultation_notifies_with_reason(): void
    {
        Notification::fake();
        $this->actingAs($this->userWithRole(Access::RECEPTIONIST));
        $consultation = Consultation::factory()->create();

        Livewire::test(ListConsultations::class)
            ->callTableAction('decline', $consultation, data: ['reason' => 'Outside our practice areas.']);

        $this->assertSame('declined', $consultation->fresh()->status);
        Notification::assertSentTo(new AnonymousNotifiable, ConsultationStatusUpdated::class,
            fn ($n, $c, $notifiable) => in_array('Reason: Outside our practice areas.', $n->toMail($notifiable)->introLines, true));
    }

    public function test_consultation_message_is_not_written_to_audit_log(): void
    {
        Consultation::factory()->create(['message' => 'SECRET-DETAIL']);

        $this->assertStringNotContainsString('SECRET-DETAIL', \App\Models\ActivityLog::pluck('properties')->toJson());
    }

    public function test_inquiry_can_be_marked_handled(): void
    {
        $staff = $this->userWithRole(Access::RECEPTIONIST);
        $this->actingAs($staff);
        $inquiry = ContactInquiry::factory()->create();

        Livewire::test(ListContactInquiries::class)->callTableAction('markHandled', $inquiry);

        $inquiry->refresh();
        $this->assertSame('handled', $inquiry->status);
        $this->assertNotNull($inquiry->handled_at);
        $this->assertSame($staff->id, $inquiry->handled_by);
    }

    public function test_advocate_cannot_open_contact_inquiries(): void
    {
        $this->actingAs($this->userWithRole(Access::ADVOCATE))
            ->get('/admin/contact-inquiries')
            ->assertForbidden();
    }

    public function test_application_status_change_notifies_applicant(): void
    {
        Notification::fake();
        $reviewer = $this->userWithRole(Access::MANAGING_PARTNER);
        $this->actingAs($reviewer);
        $application = CareerApplication::factory()->create(['email' => 'applicant@example.test', 'internal_notes' => null]);

        Livewire::test(EditCareerApplication::class, ['record' => $application->getRouteKey()])
            ->fillForm(['status' => 'shortlisted', 'internal_notes' => 'PRIVATE-NOTE strong candidate'])
            ->call('save')
            ->assertHasNoFormErrors();

        $application->refresh();
        $this->assertSame('shortlisted', $application->status);
        $this->assertSame($reviewer->id, $application->reviewed_by);
        Notification::assertSentTo(new AnonymousNotifiable, ApplicationStatusUpdated::class,
            function ($n, $c, $notifiable) {
                $mail = $n->toMail($notifiable);

                return $notifiable->routes['mail'] === 'applicant@example.test'
                    && ! str_contains(implode(' ', $mail->introLines), 'PRIVATE-NOTE');
            });
    }

    public function test_notes_only_change_does_not_notify_applicant(): void
    {
        Notification::fake();
        $this->actingAs($this->userWithRole(Access::MANAGING_PARTNER));
        $application = CareerApplication::factory()->create(['status' => 'under_review']);

        Livewire::test(EditCareerApplication::class, ['record' => $application->getRouteKey()])
            ->fillForm(['internal_notes' => 'Call references'])
            ->call('save');

        Notification::assertNothingSent();
    }

    public function test_cv_download_works_for_managing_partner_and_is_audited(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('applications/cv-1.pdf', '%PDF-1.4 test');
        $partner = $this->userWithRole(Access::MANAGING_PARTNER);
        $this->actingAs($partner);
        $application = CareerApplication::factory()->create(['cv_path' => 'applications/cv-1.pdf', 'cv_original_name' => 'Jane CV.pdf']);

        Livewire::test(ListCareerApplications::class)
            ->callTableAction('downloadCv', $application)
            ->assertFileDownloaded('Jane CV.pdf');

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'application.cv-downloaded',
            'subject_id' => $application->id,
            'user_id' => $partner->id,
        ]);
    }

    public function test_cv_download_is_denied_without_careers_permission(): void
    {
        $application = CareerApplication::factory()->create();
        $editor = $this->userWithRole(Access::CONTENT_EDITOR);

        $this->assertFalse($editor->can('downloadCv', $application));
        $this->actingAs($editor)->get('/admin/career-applications')->assertForbidden();
        $this->actingAs($editor)->get('/admin/career-applications/'.$application->id.'/edit')->assertForbidden();
    }

    public function test_cv_is_stored_privately_and_erased_with_the_application(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('applications/cv-2.pdf', 'x');
        $application = CareerApplication::factory()->create(['cv_path' => 'applications/cv-2.pdf']);

        $application->forceDelete();

        Storage::disk('local')->assertMissing('applications/cv-2.pdf');
    }

    public function test_newsletter_export_contains_only_active_subscribers(): void
    {
        $this->actingAs($this->userWithRole(Access::CONTENT_EDITOR));
        NewsletterSubscriber::factory()->confirmed()->create(['email' => 'active@example.test']);
        NewsletterSubscriber::factory()->create(['email' => 'pending@example.test']);
        NewsletterSubscriber::factory()->confirmed()->create(['email' => 'gone@example.test', 'unsubscribed_at' => now()]);
        NewsletterSubscriber::factory()->confirmed()->create(['email' => 'evil@example.test', 'name' => '=HYPERLINK("x")']);

        $response = \App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource::exportActive();
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('active@example.test', $csv);
        $this->assertStringNotContainsString('pending@example.test', $csv);
        $this->assertStringNotContainsString('gone@example.test', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'Formula values must be neutralised');

        Livewire::test(ListNewsletterSubscribers::class)->callAction('exportCsv')->assertFileDownloaded();
    }
}
