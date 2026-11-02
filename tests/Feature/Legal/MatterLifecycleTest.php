<?php

namespace Tests\Feature\Legal;

use App\Filament\Resources\Matters\Pages\CreateMatter;
use App\Filament\Resources\Matters\Pages\ViewMatter;
use App\Legal\ConflictSearch;
use App\Legal\MatterLifecycle;
use App\Models\Client;
use App\Models\Engagement;
use App\Models\Matter;
use App\Models\User;
use App\Notifications\Practice\EngagementAwaitingApproval;
use App\Support\Access;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MatterLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $advocate;
    private User $partner;
    private MatterLifecycle $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->withoutMfaEnforcement();
        $this->advocate = $this->userWithRole(Access::ADVOCATE);
        $this->partner = $this->userWithRole(Access::PARTNER);
        $this->lifecycle = app(MatterLifecycle::class);
    }

    private function openMatter(): Matter
    {
        return $this->lifecycle->open(['title' => 'Breach of contract', 'client_id' => Client::factory()->create(['status' => 'prospect'])->id], $this->advocate);
    }

    private function clearConflicts(Matter $matter): void
    {
        $check = app(ConflictSearch::class)->run([['name' => $matter->client->name, 'role' => 'prospective_client']], $this->advocate, $matter->client, $matter);
        app(ConflictSearch::class)->review($check, $this->partner, 'cleared', 'No relevant matches found in the search.');
    }

    private function approvedEngagement(Matter $matter): Engagement
    {
        $engagement = $matter->engagements()->create(['client_id' => $matter->client_id, 'fee_arrangement' => 'fixed', 'fixed_fee' => 5000000, 'currency' => 'UGX', 'scope' => 'Represent the client in the suit']);
        $this->lifecycle->submitEngagement($engagement, $this->advocate);
        $this->lifecycle->decideEngagement($engagement->refresh(), $this->partner, true);

        return $engagement->refresh();
    }

    public function test_opening_a_matter_creates_intake_history_and_puts_lead_on_team(): void
    {
        $matter = $this->openMatter();

        $this->assertSame('intake', $matter->status);
        $this->assertMatchesRegularExpression('/^MAT-\d{4}-[A-Z0-9]{6}$/', $matter->reference);
        $this->assertTrue($matter->assignments()->where('user_id', $this->advocate->id)->where('role', 'lead')->exists());
        $this->assertDatabaseHas('matter_status_history', ['matter_id' => $matter->id, 'to_status' => 'intake', 'user_id' => $this->advocate->id]);
    }

    public function test_full_lifecycle_requires_conflict_clearance_and_approved_engagement(): void
    {
        $matter = $this->openMatter();

        $this->clearConflicts($matter);
        $this->assertSame('conflict_review', $matter->fresh()->status, 'Running a check moves intake to conflict review');

        $this->lifecycle->transition($matter->fresh(), 'awaiting_engagement', $this->advocate);
        $this->approvedEngagement($matter);
        $this->lifecycle->transition($matter->fresh(), 'active', $this->advocate);

        $matter->refresh();
        $this->assertSame('active', $matter->status);
        $this->assertNotNull($matter->opened_at);
        $this->assertSame('active', $matter->client->fresh()->status, 'Prospect becomes an active client');

        $this->lifecycle->transition($matter, 'stayed', $this->advocate, 'Stayed pending appeal');
        $this->lifecycle->transition($matter->fresh(), 'closed', $this->advocate, 'Judgment delivered and satisfied');
        $this->lifecycle->transition($matter->fresh(), 'archived', $this->advocate);

        $this->assertSame(
            ['intake', 'conflict_review', 'awaiting_engagement', 'active', 'stayed', 'closed', 'archived'],
            $matter->statusHistory()->reorder()->orderBy('id')->pluck('to_status')->all(),
        );
        $this->assertSame('Judgment delivered and satisfied', $matter->fresh()->closure_reason);
    }

    public function test_cannot_skip_to_engagement_without_cleared_conflict_check(): void
    {
        $matter = $this->openMatter();
        $this->lifecycle->transition($matter, 'conflict_review', $this->advocate, 'Starting review');

        $this->expectException(ValidationException::class);
        $this->lifecycle->transition($matter->fresh(), 'awaiting_engagement', $this->advocate);
    }

    public function test_a_conflict_found_does_not_allow_engagement(): void
    {
        $matter = $this->openMatter();
        $check = app(ConflictSearch::class)->run([['name' => 'Someone', 'role' => 'opposing']], $this->advocate, null, $matter);
        app(ConflictSearch::class)->review($check, $this->partner, 'conflict_found', 'We act for the opposing party.');

        $this->expectException(ValidationException::class);
        $this->lifecycle->transition($matter->fresh(), 'awaiting_engagement', $this->advocate);
    }

    public function test_cannot_activate_without_approved_engagement(): void
    {
        $matter = $this->openMatter();
        $this->clearConflicts($matter);
        $this->lifecycle->transition($matter->fresh(), 'awaiting_engagement', $this->advocate);

        $this->expectException(ValidationException::class);
        $this->lifecycle->transition($matter->fresh(), 'active', $this->advocate);
    }

    public function test_invalid_transitions_and_missing_reasons_are_rejected(): void
    {
        $matter = $this->openMatter();

        try {
            $this->lifecycle->transition($matter, 'active', $this->advocate);
            $this->fail('intake → active must be rejected');
        } catch (ValidationException) {
        }

        $this->expectException(ValidationException::class);
        $this->lifecycle->transition($matter->fresh(), 'closed', $this->advocate); // reason required
    }

    public function test_outsiders_cannot_change_status(): void
    {
        $matter = $this->openMatter();

        $this->expectException(AuthorizationException::class);
        $this->lifecycle->transition($matter, 'conflict_review', $this->userWithRole(Access::ADVOCATE), 'x');
    }

    public function test_engagement_approval_requires_permission_and_a_different_person(): void
    {
        Notification::fake();
        $matter = $this->openMatter();
        $this->clearConflicts($matter);
        $engagement = $matter->engagements()->create(['client_id' => $matter->client_id, 'fee_arrangement' => 'hourly', 'hourly_rate' => 300000, 'currency' => 'UGX', 'scope' => 'Advice']);

        $this->lifecycle->submitEngagement($engagement, $this->advocate);
        $engagement->refresh();
        $this->assertSame('pending_approval', $engagement->status);
        $this->assertNotNull($engagement->conflict_check_id, 'Engagement records which conflict check cleared it');

        // Partner can approve but can't see the matter yet? Partner has view-all → can see.
        Notification::assertSentTo($this->partner, EngagementAwaitingApproval::class);

        $this->assertFalse($this->advocate->can('approve', $engagement), 'Advocates lack engagements.approve');

        $partnerWhoSubmitted = $engagement->submitted_by;
        $this->assertNotSame($partnerWhoSubmitted, $this->partner->id);
        $this->lifecycle->decideEngagement($engagement, $this->partner, true);
        $this->assertSame('approved', $engagement->fresh()->status);
        $this->assertFalse($this->partner->can('update', $engagement->fresh()), 'Approved engagements are locked');
    }

    public function test_approvers_cannot_approve_their_own_submission(): void
    {
        $matter = $this->openMatter();
        $this->clearConflicts($matter);
        $matter->assignments()->create(['user_id' => $this->partner->id, 'role' => 'associate']);
        $engagement = $matter->engagements()->create(['client_id' => $matter->client_id, 'fee_arrangement' => 'pro_bono', 'currency' => 'UGX', 'scope' => 'Pro bono advice']);
        $this->lifecycle->submitEngagement($engagement, $this->partner);

        $this->assertFalse($this->partner->can('approve', $engagement->fresh()));
    }

    public function test_engagement_needs_cleared_conflict_check_to_be_submitted(): void
    {
        $matter = $this->openMatter();
        $engagement = $matter->engagements()->create(['client_id' => $matter->client_id, 'fee_arrangement' => 'pro_bono', 'currency' => 'UGX', 'scope' => 'x']);

        $this->expectException(ValidationException::class);
        $this->lifecycle->submitEngagement($engagement, $this->advocate);
    }

    public function test_declining_requires_a_reason(): void
    {
        $matter = $this->openMatter();
        $this->clearConflicts($matter);
        $engagement = $matter->engagements()->create(['client_id' => $matter->client_id, 'fee_arrangement' => 'pro_bono', 'currency' => 'UGX', 'scope' => 'x']);
        $this->lifecycle->submitEngagement($engagement, $this->advocate);

        $this->expectException(ValidationException::class);
        $this->lifecycle->decideEngagement($engagement->refresh(), $this->partner, false, '');
    }

    public function test_status_history_cannot_be_altered(): void
    {
        $matter = $this->openMatter();
        $entry = $matter->statusHistory()->first();

        $entry->reason = 'tampered';
        $entry->save();
        $entry->delete();

        $this->assertDatabaseHas('matter_status_history', ['id' => $entry->id, 'reason' => 'Matter opened']);
    }

    public function test_cannot_archive_with_open_tasks_or_deadlines(): void
    {
        $matter = Matter::factory()->status('closed')->ledBy($this->advocate)->withTeam([$this->advocate->id => 'lead'])->create();
        $matter->deadlines()->create(['title' => 'File submissions', 'due_at' => now()->addWeek()]);

        $this->expectException(ValidationException::class);
        $this->lifecycle->transition($matter, 'archived', $this->advocate);
    }

    public function test_matter_opened_through_admin_ui_and_status_changed_with_reason(): void
    {
        $client = Client::factory()->create();
        $this->actingAs($this->advocate);

        Livewire::test(CreateMatter::class)
            ->fillForm(['title' => 'UI matter', 'client_id' => $client->id, 'priority' => 'high', 'lead_user_id' => $this->advocate->id, 'is_restricted' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $matter = Matter::where('title', 'UI matter')->sole();
        $this->assertSame('intake', $matter->status);
        $this->assertFalse($matter->is_restricted, 'Only matter managers may create ethical walls');

        Livewire::test(ViewMatter::class, ['record' => $matter->getRouteKey()])
            ->callAction('changeStatus', data: ['status' => 'conflict_review', 'reason' => 'Begin checks'])
            ->assertHasNoActionErrors();

        $this->assertSame('conflict_review', $matter->fresh()->status);
    }
}
