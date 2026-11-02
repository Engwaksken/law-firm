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

    /*
     | Confidential legal documents. The disk MUST be private (never "public").
     | Download links are signed and expire after `link_ttl_minutes`.
     */
    'documents' => [
        'disk' => env('FIRM_DOCUMENTS_DISK', 'local'),
        'max_upload_kb' => (int) env('FIRM_DOCUMENTS_MAX_KB', 51200),
        'link_ttl_minutes' => (int) env('FIRM_DOCUMENT_LINK_TTL', 5),
    ],

    /*
     | Malware scanning of uploads. "clamav" talks to a clamd daemon; "none"
     | records files as "not_scanned" (shown as a warning to staff).
     */
    'scanner' => [
        'driver' => env('FIRM_SCANNER', 'none'),
        'clamav_socket' => env('CLAMAV_SOCKET', 'tcp://127.0.0.1:3310'),
        'clamav_timeout' => (int) env('CLAMAV_TIMEOUT', 30),
    ],

    /*
     | Default calendar reminder schedule (days before). These are notification
     | preferences, NOT legal deadline calculations (spec §9).
     */
    'reminder_days' => [14, 7, 3, 1, 0],

    'security' => [
        // Require MFA enrolment for the roles in App\Support\Access::MFA_REQUIRED_ROLES.
        'enforce_mfa' => (bool) env('FIRM_ENFORCE_MFA', true),
        'password_min_length' => (int) env('FIRM_PASSWORD_MIN_LENGTH', 12),
    ],

];
