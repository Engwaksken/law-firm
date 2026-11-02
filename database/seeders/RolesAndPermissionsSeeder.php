<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Access;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent and safe to re-run on a live system:
 *  - new roles receive their default permissions;
 *  - permissions newly added to App\Support\Access are granted to their default roles;
 *  - permission changes administrators made in the panel are left alone.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $newPermissions = [];
        foreach (Access::permissionNames() as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if ($permission->wasRecentlyCreated) {
                $newPermissions[] = $name;
            }
        }

        foreach (Access::rolePermissions() as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $grant = $role->wasRecentlyCreated ? $permissions : array_intersect($permissions, $newPermissions);
            if ($grant !== []) {
                $role->givePermissionTo($grant);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
