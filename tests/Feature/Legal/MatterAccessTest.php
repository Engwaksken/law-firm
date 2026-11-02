<?php

namespace Tests\Feature\Legal;

use App\Filament\Resources\Matters\Pages\ListMatters;
use App\Models\CaseNote;
use App\Models\Matter;
use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Spec §8/§15/§23: confidential case records must not be visible to unrelated
 * staff; record-level checks, not menu hiding.
 */
class MatterAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $lead;
    private User $teamMember;
    private User $outsider;
    private User $partner;   // has matters.view-all
    private Matter $matter;
    private Matter $restricted;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->withoutMfaEnforcement();

        $this->lead = $this->userWithRole(Access::ADVOCATE);
        $this->teamMember = $this->userWithRole(Access::PARALEGAL);
        $this->outsider = $this->userWithRole(Access::ADVOCATE);
        $this->partner = $this->userWithRole(Access::MANAGING_PARTNER);

        $this->matter = Matter::factory()->active()->ledBy($this->lead)
            ->withTeam([$this->lead->id => 'lead', $this->teamMember->id => 'paralegal'])
            ->create(['title' => 'Ordinary Matter']);
        $this->restricted = Matter::factory()->active()->restricted()->ledBy($this->lead)
            ->withTeam([$this->lead->id => 'lead'])
            ->create(['title' => 'Walled Matter']);
    }

    public function test_assigned_users_see_their_matter_and_outsiders_do_not(): void
    {
        $this->assertTrue($this->lead->can('view', $this->matter));
        $this->assertTrue($this->teamMember->can('view', $this->matter));
        $this->assertFalse($this->outsider->can('view', $this->matter));

        $this->actingAs($this->outsider)->get("/admin/matters/{$this->matter->id}")->assertNotFound();
        $this->actingAs($this->lead)->get("/admin/matters/{$this->matter->id}")->assertOk()->assertSee('Ordinary Matter');
    }

    public function test_matter_list_and_search_only_contain_visible_matters(): void
    {
        $this->actingAs($this->outsider);
        Livewire::test(ListMatters::class)
            ->removeTableFilter('mine')
            ->assertCanNotSeeTableRecords([$this->matter, $this->restricted]);

        $this->actingAs($this->teamMember);
        Livewire::test(ListMatters::class)
            ->assertCanSeeTableRecords([$this->matter])
            ->assertCanNotSeeTableRecords([$this->restricted]);

        $this->assertSame(0, Matter::query()->visibleTo($this->outsider)->count());
    }

    public function test_view_all_permission_sees_unrestricted_matters_but_not_ethical_walls(): void
    {
        $this->assertTrue($this->partner->can('view', $this->matter));
        $this->assertFalse($this->partner->can('view', $this->restricted));

        $visible = Matter::query()->visibleTo($this->partner)->pluck('id');
        $this->assertContains($this->matter->id, $visible);
        $this->assertNotContains($this->restricted->id, $visible);
        $this->actingAs($this->partner)->get("/admin/matters/{$this->restricted->id}")->assertNotFound();
    }

    public function test_system_owner_bypass_does_not_breach_ethical_walls(): void
    {
        $owner = $this->userWithRole(Access::SYSTEM_OWNER);

        $this->assertTrue($owner->can('view', $this->matter), 'Owner sees ordinary matters (view-all)');
        $this->assertFalse($owner->can('view', $this->restricted), 'Owner does not see restricted matters unless assigned');
        $this->assertFalse($owner->can('update', $this->restricted));
        $this->assertNotContains($this->restricted->id, Matter::query()->visibleTo($owner)->pluck('id'));
        // Normal Gate::before bypass still applies elsewhere.
        $this->assertTrue($owner->can('users.manage'));
    }

    public function test_assignment_to_restricted_matter_grants_access(): void
    {
        $this->restricted->assignments()->create(['user_id' => $this->partner->id, 'role' => 'associate']);

        $this->assertTrue($this->partner->fresh()->can('view', $this->restricted));
    }

    public function test_case_notes_follow_matter_access(): void
    {
        $this->actingAs($this->lead);
        $note = CaseNote::create(['matter_id' => $this->matter->id, 'body' => 'Privileged strategy']);
        $wallNote = CaseNote::create(['matter_id' => $this->restricted->id, 'body' => 'Walled strategy']);

        $this->assertTrue($this->teamMember->can('view', $note));
        $this->assertFalse($this->outsider->can('view', $note));
        $this->assertFalse($this->partner->can('view', $wallNote));
        $this->assertSame([$note->id], CaseNote::query()->visibleTo($this->teamMember)->pluck('id')->all());
        $this->assertSame(0, CaseNote::query()->visibleTo($this->outsider)->count());
    }

    public function test_case_notes_are_encrypted_at_rest_and_kept_out_of_audit_log(): void
    {
        $this->actingAs($this->lead);
        $note = CaseNote::create(['matter_id' => $this->matter->id, 'body' => 'Client admitted the debt']);

        $raw = \DB::table('case_notes')->where('id', $note->id)->value('body');
        $this->assertStringNotContainsString('admitted', $raw);
        $this->assertStringNotContainsString('admitted', \App\Models\ActivityLog::pluck('properties')->toJson());
        $this->assertSame('Client admitted the debt', $note->fresh()->body);
    }

    public function test_only_authors_edit_their_notes_and_paralegals_without_permission_cannot_write(): void
    {
        $this->actingAs($this->lead);
        $note = CaseNote::create(['matter_id' => $this->matter->id, 'body' => 'x']);
        $associate = $this->userWithRole(Access::LEGAL_ASSOCIATE);
        $this->matter->assignments()->create(['user_id' => $associate->id, 'role' => 'associate']);

        $this->assertTrue($this->lead->can('update', $note));
        $this->assertFalse($associate->fresh()->can('update', $note));
        $this->assertTrue(\Gate::forUser($associate->fresh())->allows('createFor', [CaseNote::class, $this->matter]));
        // Paralegals have no case-notes.edit permission.
        $this->assertFalse(\Gate::forUser($this->teamMember)->allows('createFor', [CaseNote::class, $this->matter]));
    }

    public function test_deactivated_team_member_loses_access(): void
    {
        $this->teamMember->update(['is_active' => false]);

        $this->assertFalse($this->teamMember->fresh()->can('view', $this->matter));
        $this->assertSame(0, Matter::query()->visibleTo($this->teamMember->fresh())->count());
    }

    public function test_matter_description_is_encrypted(): void
    {
        $this->matter->update(['description' => 'Sensitive summary of facts']);

        $this->assertStringNotContainsString('Sensitive', \DB::table('matters')->where('id', $this->matter->id)->value('description'));
    }

    public function test_matters_cannot_be_deleted_by_anyone(): void
    {
        $owner = $this->userWithRole(Access::SYSTEM_OWNER);
        $this->matter->assignments()->create(['user_id' => $owner->id, 'role' => 'associate']);

        $this->assertFalse($owner->can('delete', $this->matter));
        $this->assertFalse($this->lead->can('delete', $this->matter));
    }

    public function test_client_page_lists_only_matters_the_viewer_can_see(): void
    {
        $receptionist = $this->userWithRole(Access::RECEPTIONIST); // clients.view, no matter access
        $client = $this->matter->client;

        $this->actingAs($receptionist)->get("/admin/clients/{$client->id}")->assertOk()
            ->assertDontSee('Ordinary Matter')->assertSee('No matters you can access.');
    }
}
