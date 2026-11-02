<?php

namespace Tests\Feature\Legal;

use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Models\Client;
use App\Models\Matter;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->withoutMfaEnforcement();
    }

    public function test_client_intake_with_contacts_and_reference(): void
    {
        $this->actingAs($this->userWithRole(Access::ADVOCATE));

        Livewire::test(CreateClient::class)
            ->fillForm([
                'type' => 'corporate', 'status' => 'prospect', 'name' => 'Lakeside Foods (U) Ltd',
                'registration_number' => '80020001234567', 'email' => 'legal@lakeside.test',
                'id_type' => 'other', 'id_number' => 'TIN-1009988776',
                'contacts' => [['name' => 'Sarah Achieng', 'position' => 'Company Secretary', 'is_authorized_representative' => true, 'is_primary' => true]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $client = Client::sole();
        $this->assertMatchesRegularExpression('/^CL-\d{4}-[A-Z0-9]{6}$/', $client->reference);
        $this->assertSame('lakeside foods', $client->normalized_name);
        $this->assertTrue($client->contacts()->where('is_authorized_representative', true)->exists());
    }

    public function test_identification_is_encrypted_masked_and_not_audited(): void
    {
        $client = Client::factory()->create(['id_type' => 'national_id', 'id_number' => 'CM90012345ABCD']);

        $this->assertStringNotContainsString('CM900', \DB::table('clients')->where('id', $client->id)->value('id_number'));
        $this->assertSame('••••••••••ABCD', $client->maskedIdNumber());
        $this->assertStringNotContainsString('CM900', \App\Models\ActivityLog::pluck('properties')->toJson());

        $this->actingAs($this->userWithRole(Access::RECEPTIONIST))   // clients.view only
            ->get("/admin/clients/{$client->id}")->assertOk()
            ->assertDontSee('CM90012345ABCD')->assertSee('ABCD');
    }

    public function test_clients_with_matters_cannot_be_deleted(): void
    {
        $manager = $this->userWithRole(Access::PARTNER);
        $client = Client::factory()->create();
        $this->assertTrue($manager->can('delete', $client));

        Matter::factory()->create(['client_id' => $client->id]);
        $this->assertFalse($manager->can('delete', $client->fresh()));
    }

    public function test_client_access_requires_permission(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->userWithRole(Access::CONTENT_EDITOR))->get('/admin/clients')->assertForbidden();
        $this->actingAs($this->userWithRole(Access::FINANCE_OFFICER))->get('/admin/clients')->assertOk();
        $this->actingAs($this->userWithRole(Access::FINANCE_OFFICER))->get('/admin/clients/create')->assertForbidden();
    }
}
