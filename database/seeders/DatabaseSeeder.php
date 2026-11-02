<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\User;
use App\Support\Access;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        // Default branding/contact values, only for keys not yet set.
        $existing = Settings::all();
        Settings::setMany(collect(Settings::DEFINITIONS)
            ->reject(fn ($def, $key) => array_key_exists($key, $existing))
            ->map(fn ($def) => $def['default'])
            ->all());

        Office::firstOrCreate(['code' => 'HQ'], ['name' => 'Head Office', 'city' => 'Kampala', 'is_head_office' => true]);

        $this->seedSystemOwner();
    }

    /**
     * The first System Owner comes from config/firm.php (SYSTEM_OWNER_* env vars).
     * Without a password a random one is generated and printed once.
     */
    private function seedSystemOwner(): void
    {
        $config = config('firm.system_owner');
        $email = $config['email'];
        if (! $email || User::role(Access::SYSTEM_OWNER)->exists()) {
            if (! $email) {
                $this->command?->warn('SYSTEM_OWNER_EMAIL not set: skipped creating the System Owner account.');
            }

            return;
        }

        $password = $config['password'] ?: Str::password(20);
        $owner = User::firstOrCreate(['email' => $email], [
            'name' => $config['name'],
            'password' => $password,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $owner->assignRole(Access::SYSTEM_OWNER);

        if (! $config['password']) {
            $this->command?->info("System Owner created: {$email} / {$password}  (change it after first login)");
        }
    }
}
