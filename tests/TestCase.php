<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedAccess(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        return User::factory()->withRole($role)->create($attributes);
    }

    /** Most tests exercise authorization, not MFA enrolment; MFA has its own tests. */
    protected function withoutMfaEnforcement(): void
    {
        config(['firm.security.enforce_mfa' => false]);
    }
}
