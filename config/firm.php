<?php

return [

    /*
     | First System Owner account, created by `php artisan db:seed` when no
     | System Owner exists yet. Leave the password empty to have one generated.
     */
    'system_owner' => [
        'name' => env('SYSTEM_OWNER_NAME', 'System Owner'),
        'email' => env('SYSTEM_OWNER_EMAIL'),
        'password' => env('SYSTEM_OWNER_PASSWORD'),
    ],

    'security' => [
        // Require MFA enrolment for the roles in App\Support\Access::MFA_REQUIRED_ROLES.
        'enforce_mfa' => (bool) env('FIRM_ENFORCE_MFA', true),
        'password_min_length' => (int) env('FIRM_PASSWORD_MIN_LENGTH', 12),
    ],

];
