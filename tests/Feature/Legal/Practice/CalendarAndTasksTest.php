<?php

namespace Tests\Feature\Legal\Practice;

use App\Filament\Resources\Hearings\Pages\ManageHearings;
use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Models\Appointment;
use App\Models\Deadline;
use App\Models\Hearing;
use App\Models\Matter;
use App\Models\Task;
use App\Notifications\Practice\CalendarReminder;
use App\Notifications\Practice\TaskAssigned;
use App\Notifications\Practice\TaskOverdueEscalation;
use App\Support\Access;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

class CalendarAndTasksTest extends PracticeTestCase
{
    public function test_outsider_cannot_see_matter_tasks_or_hearings(): void
    {
        $task = Task::factory()->create(['matter_id' => $this->matter->id, 'assigned_to' => $this->lead->id, 'created_by' => $this->lead->id]);
        $hearing = Hearing::factory()->create(['matter_id' => $this->matter->id]);

        $this->assertFalse($this->outsider->can('view', $task));
        $this->assertFalse($this->outsider->can('view', $hearing));
        $this->assertSame(0, Task::query()->visibleTo($this->outsider)->count());
        $this->assertSame(0, Hearing::query()->visibleTo($this->outsider)->count());

        $this->actingAs($this->outsider);
        Livewire::test(ListTasks::class)->removeTableFilter('open')->assertCanNotSeeTableRecords([$task]);
        Livewire::test(ManageHearings::class)->assertCanNotSeeTableRecords([$hearing]);
        $this->get(\App\Filament\Resources\Tasks\TaskResource::getUrl('edit', ['record' => $task]))->assertNotFound();
    }

    public function test_lead_sees_matter_tasks_and_hearings(): void
    {
        $task = Task::factory()->create(['matter_id' => $this->matter->id]);
        $hearing = Hearing::factory()->create(['matter_id' => $this->matter->id]);

        $this->actingAs($this->lead);
        Livewire::test(ListTasks::class)->assertCanSeeTableRecords([$task]);
        Livewire::test(ManageHearings::class)->assertCanSeeTableRecords([$hearing]);
    }

    public function test_personal_task_is_visible_to_assignee_and_creator_only(): void
    {
        $this->actingAs($this->lead);
        $task = Task::create(['title' => 'Call registry', 'assigned_to' => $this->outsider->id]);

        $this->assertTrue($this->lead->can('view', $task));
        $this->assertTrue($this->outsider->can('view', $task));
        $this->assertFalse($this->userWithRole(Access::MANAGING_PARTNER)->can('view', $task));
    }

    public function test_creating_a_matter_task_requires_contribute_rights_and_notifies_assignee(): void
    {
        Notification::fake();
        $assistant = $this->userWithRole(Access::LEGAL_ASSISTANT);
        $this->matter->assignments()->create(['user_id' => $assistant->id, 'role' => 'assistant']);

        $this->actingAs($this->lead);
        Livewire::test(CreateTask::class)
            ->fillForm(['title' => 'Draft affidavit', 'matter_id' => $this->matter->id, 'assigned_to' => $assistant->id, 'priority' => 'high', 'status' => 'open'])
            ->call('create')->assertHasNoFormErrors();

        $task = Task::sole();
        Notification::assertSentTo($assistant, TaskAssigned::class);

        // Outsider tampers the matter id: blocked server-side.
        $this->actingAs($this->outsider);
        Livewire::test(CreateTask::class)
            ->fillForm(['title' => 'Sneaky', 'priority' => 'normal', 'status' => 'open'])
            ->set('data.matter_id', $this->matter->id)
            ->call('create');
        $this->assertSame(1, Task::count());
    }

    public function test_assignee_must_be_on_the_matter_team(): void
    {
        $this->actingAs($this->lead);
        Livewire::test(CreateTask::class)
            ->fillForm(['title' => 'X', 'matter_id' => $this->matter->id, 'priority' => 'normal', 'status' => 'open'])
            ->set('data.assigned_to', $this->outsider->id)
            ->call('create')
            ->assertHasFormErrors(['assigned_to']);
    }

    public function test_task_restricted_matter_mail_hides_title(): void
    {
        $walled = Matter::factory()->active()->restricted()->ledBy($this->lead)->create();
        $task = Task::factory()->create(['matter_id' => $walled->id, 'title' => 'Secret merger memo', 'assigned_to' => $this->lead->id]);

        $mail = (new TaskAssigned($task))->toMail($this->lead);

        $this->assertStringNotContainsString('Secret merger memo', implode(' ', $mail->introLines));
        $this->assertStringContainsString($walled->reference, implode(' ', $mail->introLines));
    }

    public function test_reminders_are_sent_once_per_offset(): void
    {
        Notification::fake();
        $assistant = $this->userWithRole(Access::LEGAL_ASSISTANT);
        $this->matter->assignments()->create(['user_id' => $assistant->id, 'role' => 'assistant']);
        Hearing::factory()->create(['matter_id' => $this->matter->id, 'starts_at' => now()->addDays(3)->setTime(9, 0)]);
        Hearing::factory()->create(['matter_id' => $this->matter->id, 'starts_at' => now()->addDays(5)->setTime(9, 0)]); // 5 not an offset
        Deadline::factory()->create(['matter_id' => $this->matter->id, 'due_at' => now()->addDay()->setTime(17, 0), 'responsible_user_id' => $assistant->id]);

        $this->artisan('practice:send-reminders')->assertSuccessful();
        $this->artisan('practice:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($this->lead, CalendarReminder::class, 1);       // hearing (3 days)
        Notification::assertSentToTimes($assistant, CalendarReminder::class, 2);        // hearing + own deadline (1 day)
        Notification::assertNotSentTo($this->outsider, CalendarReminder::class);
        $this->assertSame(3, \DB::table('calendar_reminders_sent')->count());
    }

    public function test_custom_reminder_days_and_completed_deadlines(): void
    {
        Notification::fake();
        Deadline::factory()->create(['matter_id' => $this->matter->id, 'due_at' => now()->addDays(3), 'reminder_days' => [7]]);
        Deadline::factory()->create(['matter_id' => $this->matter->id, 'due_at' => now()->addDays(7), 'completed_at' => now()]);

        $this->artisan('practice:send-reminders');

        Notification::assertNothingSent();
    }

    public function test_reminder_mail_is_not_confidential(): void
    {
        $this->matter->update(['title' => 'Okello v Bank of Gold']);
        $hearing = Hearing::factory()->create(['matter_id' => $this->matter->id, 'outcome' => 'Privileged strategy']);

        $mail = (new CalendarReminder($hearing->fresh(), 3))->toMail($this->lead);
        $text = $mail->subject.' '.implode(' ', $mail->introLines);

        $this->assertStringContainsString($this->matter->reference, $text);
        $this->assertStringNotContainsString('Okello', $text);
        $this->assertStringNotContainsString('Privileged', $text);
    }

    public function test_escalation_notifies_once(): void
    {
        Notification::fake();
        $assistant = $this->userWithRole(Access::LEGAL_ASSISTANT);
        $task = Task::factory()->overdue()->create(['matter_id' => $this->matter->id, 'assigned_to' => $assistant->id]);
        Task::factory()->overdue()->create(['status' => 'done', 'assigned_to' => $assistant->id]);

        $this->artisan('practice:escalate-overdue-tasks')->assertSuccessful();
        $this->artisan('practice:escalate-overdue-tasks')->assertSuccessful();

        Notification::assertSentToTimes($assistant, TaskOverdueEscalation::class, 1);
        Notification::assertSentToTimes($this->lead, TaskOverdueEscalation::class, 1);
        $this->assertNotNull($task->fresh()->escalated_at);
    }

    public function test_ics_export_contains_only_visible_entries_without_client_names(): void
    {
        $this->matter->client->update(['name' => 'Very Private Client']);
        $this->matter->update(['title' => 'Private dispute title']);
        Hearing::factory()->create(['matter_id' => $this->matter->id, 'type' => 'mention', 'starts_at' => now()->addDays(2)]);
        $hidden = Matter::factory()->active()->ledBy($this->outsider)->create();
        Hearing::factory()->create(['matter_id' => $hidden->id, 'starts_at' => now()->addDays(2)]);
        Appointment::factory()->create(['organizer_id' => $this->lead->id, 'title' => 'Lunch with Very Private Client']);

        $response = $this->actingAs($this->lead)->get(route('calendar.export'));

        $response->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $body = $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('SUMMARY:Mention · '.$this->matter->reference, $body);
        $this->assertStringNotContainsString($hidden->reference, $body);
        $this->assertStringNotContainsString('Very Private Client', $body);
        $this->assertStringNotContainsString('Private dispute title', $body);
        $this->assertSame(2, substr_count($body, 'BEGIN:VEVENT'));
    }

    public function test_ics_export_requires_sign_in(): void
    {
        $this->get(route('calendar.export'))->assertRedirect();
    }

    public function test_private_calendar_feed_works_without_session_and_can_be_revoked(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->lead->forceFill(['calendar_feed_token_hash' => hash('sha256', $token)])->save();

        $this->get(route('calendar.feed', ['token' => $token]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

        $this->lead->forceFill(['calendar_feed_token_hash' => null])->save();
        $this->get(route('calendar.feed', ['token' => $token]))->assertNotFound();
    }

    public function test_calendar_page_shows_only_visible_entries(): void
    {
        Hearing::factory()->create(['matter_id' => $this->matter->id, 'starts_at' => now()->setTime(10, 0)]);
        $hidden = Matter::factory()->active()->ledBy($this->outsider)->create();
        Hearing::factory()->create(['matter_id' => $hidden->id, 'starts_at' => now()->setTime(11, 0)]);

        $this->actingAs($this->lead)
            ->get(\App\Filament\Pages\LegalCalendar::getUrl())
            ->assertOk()
            ->assertSee($this->matter->reference)
            ->assertDontSee($hidden->reference);
    }

    public function test_hearing_adjournment_creates_next_hearing(): void
    {
        $hearing = Hearing::factory()->create(['matter_id' => $this->matter->id]);

        $this->actingAs($this->lead);
        Livewire::test(ManageHearings::class)
            ->callTableAction('adjourn', $hearing, data: ['next_at' => now()->addMonth()->toDateTimeString(), 'reason' => 'Judge on leave']);

        $this->assertSame('adjourned', $hearing->fresh()->status);
        $this->assertSame(2, Hearing::where('matter_id', $this->matter->id)->count());
    }

    public function test_task_report_counts_only_visible_tasks_and_csv_is_safe(): void
    {
        $partner = $this->userWithRole(Access::PARTNER, ['name' => '=HYPERLINK("x")']);
        $walled = Matter::factory()->active()->restricted()->ledBy($this->lead)->create();
        Task::factory()->create(['matter_id' => $this->matter->id, 'assigned_to' => $partner->id]);
        Task::factory()->create(['matter_id' => $walled->id, 'assigned_to' => $this->lead->id]);

        $this->actingAs($partner);
        $page = Livewire::test(\App\Filament\Pages\TaskReport::class);
        $rows = $page->instance()->rows();

        $this->assertCount(1, $rows);
        $this->assertSame("'=HYPERLINK(\"x\")", \App\Filament\Pages\TaskReport::safeCell($rows->first()['name']));
    }

    public function test_task_report_requires_permission(): void
    {
        $this->actingAs($this->outsider)->get(\App\Filament\Pages\TaskReport::getUrl())->assertForbidden();
    }
}
