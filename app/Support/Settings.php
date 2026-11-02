<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Cached key/value access to site_settings, with defaults for every key the
 * application reads. Admins edit these from Settings → Branding & Contact.
 */
class Settings
{
    private const CACHE_KEY = 'site_settings.all';

    /** @var array<string, array{default: mixed, type: string}> */
    public const DEFINITIONS = [
        'firm_name' => ['default' => 'Advocates & Solicitors', 'type' => 'string'],
        'firm_tagline' => ['default' => 'Trusted legal counsel', 'type' => 'string'],
        'logo_path' => ['default' => null, 'type' => 'string'],
        'logo_dark_path' => ['default' => null, 'type' => 'string'],
        'favicon_path' => ['default' => null, 'type' => 'string'],
        'color_primary' => ['default' => '#14283F', 'type' => 'string'],
        'color_secondary' => ['default' => '#660033', 'type' => 'string'],
        'color_accent' => ['default' => '#D4AF37', 'type' => 'string'],
        'color_background' => ['default' => '#FAF9F6', 'type' => 'string'],
        'color_text' => ['default' => '#252525', 'type' => 'string'],
        'font_heading' => ['default' => 'Outfit', 'type' => 'string'],
        'font_body' => ['default' => 'Poppins', 'type' => 'string'],
        'contact_email' => ['default' => null, 'type' => 'string'],
        'contact_phone' => ['default' => null, 'type' => 'string'],
        'contact_address' => ['default' => null, 'type' => 'string'],
        'office_hours' => ['default' => 'Monday – Friday, 8:00 – 17:00', 'type' => 'string'],
        'footer_text' => ['default' => null, 'type' => 'string'],
        'confidentiality_notice' => [
            'default' => 'Submitting information through this website does not create an advocate–client relationship. Please do not send confidential information until engagement has been confirmed in writing.',
            'type' => 'string',
        ],
        'social_links' => ['default' => [], 'type' => 'json'],
        'default_currency' => ['default' => 'UGX', 'type' => 'string'],
        'timezone' => ['default' => 'Africa/Kampala', 'type' => 'string'],
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::all();

        return array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== ''
            ? $all[$key]
            : ($default ?? (static::DEFINITIONS[$key]['default'] ?? null));
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('site_settings')) {
                    return [];
                }

                return SiteSetting::query()->get()
                    ->mapWithKeys(fn (SiteSetting $s) => [$s->key => static::decode($s->value, $s->type)])
                    ->all();
            });
        } catch (Throwable) {
            // Database unavailable (fresh install, CI without DB): fall back to defaults.
            return [];
        }
    }

    /** @param array<string, mixed> $values */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $type = static::DEFINITIONS[$key]['type'] ?? (is_array($value) ? 'json' : 'string');
            SiteSetting::updateOrCreate(['key' => $key], [
                'value' => $type === 'json' ? json_encode($value ?? []) : $value,
                'type' => $type,
            ]);
        }
        static::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function decode(?string $value, string $type): mixed
    {
        return match ($type) {
            'json' => json_decode((string) $value, true) ?? [],
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            default => $value,
        };
    }
}
