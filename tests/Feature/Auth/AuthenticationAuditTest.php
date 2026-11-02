<?php

namespace Tests\Feature\Auth;

use App\Support\Access;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_successful_login_is_audited_and_records_last_login(): void
    {
        $user = $this->userWithRole(Access::ADVOCATE, ['email' => 'adv@example.test']);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'adv@example.test', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('activity_logs', ['event' => 'auth.login', 'user_id' => $user->id]);
        $this->assertNotNull($user->fresh()->last_login_at);
        // Last-login bookkeeping must not add a noisy "User.updated" entry.
        $this->assertDatabaseMissing('activity_logs', ['event' => 'User.updated', 'subject_id' => $user->id]);
    }

    public function test_failed_login_is_audited_without_the_password(): void
    {
        $this->userWithRole(Access::ADVOCATE, ['email' => 'adv@example.test']);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'adv@example.test', 'password' => 'wrong-secret-123'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
        $log = \App\Models\ActivityLog::where('event', 'auth.failed')->firstOrFail();
        $this->assertSame('adv@example.test', $log->properties['email']);
        $this->assertStringNotContainsString('wrong-secret-123', json_encode($log->getAttributes()));
    }

    public function test_login_is_rate_limited(): void
    {
        $this->userWithRole(Access::ADVOCATE, ['email' => 'adv@example.test']);

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)
                ->fillForm(['email' => 'adv@example.test', 'password' => 'wrong'])
                ->call('authenticate');
        }

        Livewire::test(Login::class)
            ->fillForm(['email' => 'adv@example.test', 'password' => 'password'])
            ->call('authenticate');

        $this->assertGuest();
    }
}
