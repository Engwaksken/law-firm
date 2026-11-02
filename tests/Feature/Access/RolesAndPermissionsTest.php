<?php

namespace Tests\Feature\Access;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Access;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_seeder_creates_all_thirteen_roles_with_their_default_permissions(): void
    {
        $this->assertSame(13, Role::count());
        $this->assertSame(count(Access::permissionNames()), Permission::count());

        foreach (Access::rolePermissions() as $roleName => $expected) {
            $actual = Role::findByName($roleName)->permissions->pluck('name')->sort()->values()->all();
            sort($expected);
            $this->assertSame($expected, $actual, "Permissions for {$roleName} differ");
        }
    }

    public function test_reseeding_keeps_permissions_an_administrator_removed(): void
    {
        $role = Role::findByName(Access::RECEPTIONIST);
        $role->revokePermissionTo('clients.view');

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertFalse($role->fresh()->hasPermissionTo('clients.view'));
    }

    public function test_client_role_has_only_portal_access(): void
    {
        $client = $this->userWithRole(Access::CLIENT);

        $this->assertTrue($client->can('portal.access'));
        $this->assertFalse($client->can('clients.view'));
        $this->assertFalse($client->can('users.view'));
    }

    public function test_system_owner_bypasses_checks_but_audit_logs_stay_immutable(): void
    {
        $owner = $this->userWithRole(Access::SYSTEM_OWNER);
        $log = ActivityLog::record('test.event');

        $this->assertTrue($owner->can('anything.at-all'));
        $this->assertTrue($owner->can('view', $log));
        $this->assertFalse($owner->can('update', $log));
        $this->assertFalse($owner->can('delete', $log));
        $this->assertFalse($owner->can('create', ActivityLog::class));
    }

    public function test_activity_log_rows_cannot_be_modified_or_deleted_at_model_level(): void
    {
        $log = ActivityLog::record('test.event', description: 'original');

        $log->description = 'tampered';
        $log->save();
        $log->delete();

        $this->assertDatabaseHas('activity_logs', ['id' => $log->id, 'description' => 'original']);
    }

    public function test_super_admin_cannot_modify_or_delete_the_system_owner(): void
    {
        $owner = $this->userWithRole(Access::SYSTEM_OWNER);
        $superAdmin = $this->userWithRole(Access::SUPER_ADMIN);
        $staff = $this->userWithRole(Access::ADVOCATE);

        $this->assertFalse($superAdmin->can('update', $owner));
        $this->assertFalse($superAdmin->can('delete', $owner));
        $this->assertTrue($superAdmin->can('update', $staff));
        $this->assertTrue($owner->can('update', $superAdmin));
    }

    public function test_users_cannot_delete_themselves(): void
    {
        $admin = $this->userWithRole(Access::SUPER_ADMIN);

        $this->assertFalse($admin->can('delete', $admin));
    }

    public function test_built_in_roles_cannot_be_deleted_and_system_owner_role_cannot_be_edited(): void
    {
        $owner = $this->userWithRole(Access::SYSTEM_OWNER);
        $superAdmin = $this->userWithRole(Access::SUPER_ADMIN);
        $custom = Role::create(['name' => 'intern', 'guard_name' => 'web']);

        $this->assertFalse($superAdmin->can('delete', Role::findByName(Access::ADVOCATE)));
        $this->assertTrue($superAdmin->can('delete', $custom));
        $this->assertFalse($superAdmin->can('update', Role::findByName(Access::SYSTEM_OWNER)));
        // System Owner bypasses policies entirely.
        $this->assertTrue($owner->can('update', Role::findByName(Access::SYSTEM_OWNER)));
    }

    public function test_assignable_roles_never_exceed_the_actors_own_permissions(): void
    {
        $managingPartner = $this->userWithRole(Access::MANAGING_PARTNER);

        $assignable = Access::assignableRoles($managingPartner)->pluck('name');

        $this->assertContains(Access::ADVOCATE, $assignable);
        $this->assertContains(Access::FINANCE_OFFICER, $assignable);
        $this->assertNotContains(Access::SUPER_ADMIN, $assignable, 'Super Admin holds roles.manage, which a Managing Partner lacks');
        $this->assertNotContains(Access::SYSTEM_OWNER, $assignable);

        $owner = $this->userWithRole(Access::SYSTEM_OWNER);
        $this->assertContains(Access::SYSTEM_OWNER, Access::assignableRoles($owner)->pluck('name'));
    }
}
