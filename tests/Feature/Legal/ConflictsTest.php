<?php

namespace Tests\Feature\Legal;

use App\Filament\Resources\ConflictChecks\Pages\CreateConflictCheck;
use App\Filament\Resources\ConflictChecks\Pages\ViewConflictCheck;
use App\Legal\ConflictSearch;
use App\Models\Client;
use App\Models\ConflictCheck;
use App\Models\Matter;
use App\Models\User;
use App\Support\Access;
use App\Support\NameNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ConflictsTest extends TestCase
{
    use RefreshDatabase;

    private User $runner;
    private User $reviewer;
    private ConflictSearch $search;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->withoutMfaEnforcement();
        $this->runner = $this->userWithRole(Access::RECEPTIONIST);
        $this->reviewer = $this->userWithRole(Access::PARTNER);
        $this->search = app(ConflictSearch::class);
    }

    public function test_name_normalisation(): void
    {
        $this->assertSame('mukasa sons', NameNormalizer::normalize('Mukasa & Sons (U) Ltd.'));
        $this->assertSame('jane namukasa', NameNormalizer::normalize('Dr. Jane  NAMUKASA'));
        $this->assertSame('kampala traders', NameNormalizer::normalize('THE KAMPALA TRADERS COMPANY LIMITED'));
    }

    public function test_finds_existing_client_opposing_party_and_contact(): void
    {
        $existing = Client::factory()->corporate()->create(['name' => 'Kampala Traders Ltd']);
        $existing->contacts()->create(['name' => 'Peter Okello', 'position' => 'Director']);
        $matter = Matter::factory()->create(['title' => 'Supply dispute']);
        $matter->parties()->create(['name' => 'Nile Breweries Limited', 'party_type' => 'opposing']);

        $check = $this->search->run([
            ['name' => 'Kampala Traders Company Limited', 'role' => 'opposing'],     // = existing client (suffix noise)
            ['name' => 'Okello Peter', 'role' => 'related'],                         // contact, reversed name order
            ['name' => 'NILE BREWERIES LTD', 'role' => 'prospective_client'],       // opposing party elsewhere
            ['name' => 'Completely Unrelated Person', 'role' => 'related'],
        ], $this->runner);

        $hits = collect($check->results['hits']);
        $this->assertSame('pending_review', $check->status);
        $this->assertTrue($hits->contains(fn ($h) => $h['source'] === 'client' && $h['record_id'] === $existing->id && $h['match'] === 'exact'));
        $this->assertTrue($hits->contains(fn ($h) => $h['source'] === 'client_contact' && $h['matched_name'] === 'Peter Okello'));
        $this->assertTrue($hits->contains(fn ($h) => $h['source'] === 'matter_party' && $h['matter_reference'] === $matter->reference));
        $this->assertFalse($hits->contains(fn ($h) => $h['searched'] === 'Completely Unrelated Person'));
        $this->assertCount(4, $check->parties);
    }

    public function test_similar_spellings_are_flagged(): void
    {
        Client::factory()->create(['name' => 'Namukasa Juliet']);

        $check = $this->search->run([['name' => 'Namukassa Juliet', 'role' => 'opposing']], $this->runner);

        $hit = collect($check->results['hits'])->first();
        $this->assertNotNull($hit);
        $this->assertSame('similar', $hit['match']);
    }

    public function test_restricted_matters_are_searched_but_redacted(): void
    {
        $lead = $this->userWithRole(Access::ADVOCATE, ['name' => 'Wall Keeper']);
        $walled = Matter::factory()->restricted()->ledBy($lead)->create(['title' => 'Top secret acquisition']);
        $walled->parties()->create(['name' => 'Acme Holdings', 'party_type' => 'opposing']);

        $check = $this->search->run([['name' => 'Acme', 'role' => 'prospective_client']], $this->runner);

        $hit = collect($check->results['hits'])->firstWhere('source', 'matter_party');
        $this->assertNotNull($hit, 'Conflicts behind ethical walls must still be detected');
        $this->assertTrue($hit['restricted']);
        $this->assertSame('Restricted matter', $hit['matter_title']);
        $this->assertSame('Wall Keeper', $hit['contact']);
        $this->assertStringNotContainsString('Top secret', json_encode($check->results));
    }

    public function test_running_requires_permission_and_reviewing_requires_a_different_reviewer(): void
    {
        try {
            $this->search->run([['name' => 'X', 'role' => 'other']], $this->userWithRole(Access::FINANCE_OFFICER));
            $this->fail('Finance officers cannot run conflict checks');
        } catch (AuthorizationException) {
        }

        $check = $this->search->run([['name' => 'X Person', 'role' => 'other']], $this->runner);
        $this->assertFalse($this->runner->can('review', $check), 'Receptionist cannot review');

        $selfCheck = $this->search->run([['name' => 'Y Person', 'role' => 'other']], $this->reviewer);
        $this->assertFalse($this->reviewer->can('review', $selfCheck), 'Nobody reviews their own search');
        $this->assertTrue($this->reviewer->can('review', $check));
    }

    public function test_clearing_with_matches_requires_a_reasoned_explanation(): void
    {
        Client::factory()->create(['name' => 'Same Name']);
        $check = $this->search->run([['name' => 'Same Name', 'role' => 'opposing']], $this->runner);

        try {
            $this->search->review($check, $this->reviewer, 'cleared', 'ok');
            $this->fail('Short justification must be rejected when there are matches');
        } catch (ValidationException) {
        }

        $this->search->review($check, $this->reviewer, 'cleared', 'Different person: confirmed by date of birth and address on file.');
        $check->refresh();
        $this->assertSame('cleared', $check->status);
        $this->assertSame($this->reviewer->id, $check->reviewed_by);
        $this->assertFalse($this->reviewer->can('review', $check), 'Decisions are final');
        $this->assertDatabaseHas('activity_logs', ['event' => 'conflict.reviewed', 'subject_id' => $check->id]);
    }

    public function test_conflict_check_ui_prefills_from_client_and_records_decision(): void
    {
        $client = Client::factory()->create(['name' => 'Prefill Client']);
        $client->contacts()->create(['name' => 'Rep One']);

        $this->actingAs($this->runner);
        $this->get('/admin/conflict-checks/create?client='.$client->id)->assertOk();

        Livewire::withQueryParams(['client' => $client->id])->test(CreateConflictCheck::class)
            ->assertSet('data.client_id', $client->id)
            ->call('create')
            ->assertHasNoFormErrors();

        $check = ConflictCheck::sole();
        $this->assertEqualsCanonicalizing(['Prefill Client', 'Rep One'], $check->parties->pluck('name')->all());

        $this->actingAs($this->reviewer);
        Livewire::test(ViewConflictCheck::class, ['record' => $check->getRouteKey()])
            ->callAction('review', data: ['decision' => 'cleared', 'notes' => 'Only matches are the client and its own representative.'])
            ->assertHasNoActionErrors();
        $this->assertSame('cleared', $check->fresh()->status);
    }

    public function test_conflict_checks_cannot_be_edited_or_deleted(): void
    {
        $check = $this->search->run([['name' => 'Z Person', 'role' => 'other']], $this->runner);
        $owner = $this->userWithRole(Access::SYSTEM_OWNER);

        $this->assertFalse($this->reviewer->can('update', $check));
        $this->assertFalse($this->reviewer->can('delete', $check));
        $this->assertTrue($owner->can('delete', $check), 'System Owner bypass applies to non-matter records');
    }
}
