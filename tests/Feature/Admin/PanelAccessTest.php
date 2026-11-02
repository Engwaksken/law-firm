<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_login_page_renders(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_staff_member_without_mfa_requirement_reaches_dashboard(): void
    {
        $this->actingAs($this->userWithRole(Access::ADVOCATE))
            ->get('/admin')
            ->assertOk();
    }

    public function test_clients_are_denied_the_staff_panel(): void
    {
        $this->actingAs($this->userWithRole(Access::CLIENT))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_users_without_any_role_are_denied(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_deactivated_staff_are_denied(): void
    {
        $this->actingAs($this->userWithRole(Access::ADVOCATE, ['is_active' => false]))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_unverified_email_is_sent_to_verification_prompt(): void
    {
        $user = User::factory()->unverified()->withRole(Access::ADVOCATE)->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect('/admin/email-verification/prompt');
    }

    public function test_privileged_roles_must_set_up_mfa_before_using_the_panel(): void
    {
        $this->actingAs($this->userWithRole(Access::MANAGING_PARTNER))
            ->get('/admin')
            ->assertRedirectContains('multi-factor-authentication');
    }

    public function test_privileged_user_with_mfa_configured_reaches_dashboard(): void
    {
        $partner = $this->userWithRole(Access::MANAGING_PARTNER);
        $partner->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        $this->actingAs($partner)->get('/admin')->assertOk();
    }

    public function test_mfa_secrets_are_encrypted_at_rest(): void
    {
        $user = $this->userWithRole(Access::PARTNER);
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $user->saveAppAuthenticationRecoveryCodes(['code-one', 'code-two']);

        $raw = \DB::table('users')->where('id', $user->id)->first();

        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $raw->app_authentication_secret);
        $this->assertStringNotContainsString('code-one', $raw->app_authentication_recovery_codes);
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->fresh()->getAppAuthenticationSecret());
    }

    /** @return array<string, array{string, string, int}> */
    public static function resourceAccessMatrix(): array
    {
        return [
            'content editor cannot manage users' => [Access::CONTENT_EDITOR, '/admin/users', 403],
            'content editor cannot see audit log' => [Access::CONTENT_EDITOR, '/admin/activity-logs', 403],
            'content editor cannot change branding' => [Access::CONTENT_EDITOR, '/admin/settings/branding', 403],
            'advocate cannot manage roles' => [Access::ADVOCATE, '/admin/roles', 403],
            'receptionist cannot create offices' => [Access::RECEPTIONIST, '/admin/offices/create', 403],
            'super admin manages users' => [Access::SUPER_ADMIN, '/admin/users', 200],
            'super admin manages roles' => [Access::SUPER_ADMIN, '/admin/roles', 200],
            'super admin edits branding' => [Access::SUPER_ADMIN, '/admin/settings/branding', 200],
            'managing partner lists users' => [Access::MANAGING_PARTNER, '/admin/users', 200],
            'managing partner cannot manage roles' => [Access::MANAGING_PARTNER, '/admin/roles', 403],
            'auditor reads audit log' => [Access::AUDITOR, '/admin/activity-logs', 200],
            'auditor views users' => [Access::AUDITOR, '/admin/users', 200],
            'auditor cannot create users' => [Access::AUDITOR, '/admin/users/create', 403],
        ];
    }

    #[DataProvider('resourceAccessMatrix')]
    public function test_resource_access_follows_permissions(string $role, string $url, int $status): void
    {
        $this->withoutMfaEnforcement();

        $this->actingAs($this->userWithRole($role))->get($url)->assertStatus($status);
    }
}
