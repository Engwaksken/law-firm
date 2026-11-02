<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Validates and normalises links entered in the CMS. */
class SafeUrl
{
    /** Allowed: http(s), mailto, tel, or a site-relative path ("/contact"). Anything else → null. */
    public static function make(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return preg_match('/[\x00-\x20\x7F]/', $url) ? null : url($url);
        }
        if (str_starts_with($url, '#')) {
            return preg_match('/^#[A-Za-z0-9_-]+$/', $url) ? $url : null;
        }

        return Str::sanitizeUrl($url, ['http', 'https', 'mailto', 'tel']);
    }

    /** Validation rule closure for Filament fields. */
    public static function rule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if (filled($value) && static::make($value) === null) {
                $fail('Enter a full web address (https://…), an email (mailto:…), a phone (tel:…) or a site path starting with "/".');
            }
        };
    }
}
