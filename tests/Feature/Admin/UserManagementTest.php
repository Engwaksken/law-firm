<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->withoutMfaEnforcement();
    }

    public function test_admin_creates_a_user_with_a_role(): void
    {
        $this->actingAs($this->userWithRole(Access::SUPER_ADMIN));
        $advocateRole = Role::findByName(Access::ADVOCATE);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Jane Advocate',
                'email' => 'jane@example.test',
                'roles' => [$advocateRole->id],
                'is_active' => true,
                'password' => 'Str0ng!Passw0rd',
                'password_confirmation' => 'Str0ng!Passw0rd',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'jane@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole(Access::ADVOCATE));
        $this->assertNotSame('Str0ng!Passw0rd', $user->password, 'Password must be hashed');
    }

    public function test_managing_partner_cannot_escalate_a_user_to_super_admin(): void
    {
        $this->actingAs($this->userWithRole(Access::MANAGING_PARTNER));
        $target = $this->userWithRole(Access::ADVOCATE);

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => [Role::findByName(Access::SUPER_ADMIN)->id]])
            ->call('save')
            ->assertHasFormErrors(['roles']);

        $this->assertFalse($target->fresh()->hasRole(Access::SUPER_ADMIN));
    }

    public function test_managing_partner_can_assign_roles_within_their_permissions(): void
    {
        $this->actingAs($this->userWithRole(Access::MANAGING_PARTNER));
        $target = $this->userWithRole(Access::ADVOCATE);

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => [Role::findByName(Access::ADVOCATE)->id, Role::findByName(Access::FINANCE_OFFICER)->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($target->fresh()->hasAllRoles([Access::ADVOCATE, Access::FINANCE_OFFICER]));
    }

    public function test_role_permissions_can_be_edited_and_take_effect(): void
    {
        $this->actingAs($this->userWithRole(Access::SUPER_ADMIN));
        $role = Role::findByName(Access::RECEPTIONIST);
        $receptionist = $this->userWithRole(Access::RECEPTIONIST);
        $this->assertTrue($receptionist->can('clients.view'));

        $websiteIds = Permission::whereIn('name', ['consultations.view', 'inquiries.manage'])->pluck('id')->map(fn ($id) => (string) $id)->all();

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm([
                'permissions_website' => $websiteIds,
                'permissions_legal_practice' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $receptionist = $receptionist->fresh();
        $this->assertFalse($receptionist->can('clients.view'));
        $this->assertTrue($receptionist->can('inquiries.manage'));
        $this->assertFalse($receptionist->can('consultations.manage'));
    }

    public function test_user_password_and_mfa_secret_never_reach_the_audit_log(): void
    {
        $this->actingAs($this->userWithRole(Access::SUPER_ADMIN));
        $user = $this->userWithRole(Access::ADVOCATE);

        $user->update(['password' => 'An0ther!Passw0rd', 'name' => 'Renamed']);
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        $logs = \App\Models\ActivityLog::where('subject_type', $user->getMorphClass())->where('subject_id', $user->id)->get();
        $serialized = $logs->pluck('properties')->toJson();

        $this->assertStringContainsString('Renamed', $serialized);
        $this->assertStringNotContainsString('An0ther!Passw0rd', $serialized);
        $this->assertStringNotContainsString($user->password, $serialized);
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $serialized);
        $this->assertStringContainsString('[redacted]', $serialized);
    }

    public function test_role_assignment_is_audited(): void
    {
        $actor = $this->userWithRole(Access::SUPER_ADMIN);
        $this->actingAs($actor);
        $user = User::factory()->create();

        $user->assignRole(Access::PARALEGAL);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'access.role-assigned',
            'subject_id' => $user->id,
            'user_id' => $actor->id,
        ]);
    }
}
