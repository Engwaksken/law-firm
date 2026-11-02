<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Canonical form of a person/organisation name for conflict searches:
 * lowercase ASCII, punctuation removed, common corporate suffixes and
 * honorifics dropped, whitespace collapsed.
 *   "Mukasa & Sons (U) Ltd."  →  "mukasa sons"
 *   "Dr. Jane  NAMUKASA"      →  "jane namukasa"
 */
class NameNormalizer
{
    private const NOISE = [
        'limited', 'ltd', 'llc', 'llp', 'plc', 'inc', 'incorporated', 'corp', 'corporation', 'company', 'co',
        'u', 'uganda', 'smc', 'group', 'holdings', 'the', 'and', 'of',
        'mr', 'mrs', 'ms', 'miss', 'dr', 'prof', 'hon', 'rev', 'eng', 'sir', 'madam',
    ];

    public static function normalize(?string $name): string
    {
        $ascii = Str::of((string) $name)->ascii()->lower()->replaceMatches('/[^a-z0-9\s]/', ' ')->squish()->toString();

        $tokens = array_values(array_filter(explode(' ', $ascii), fn ($t) => $t !== '' && ! in_array($t, self::NOISE, true)));

        return implode(' ', $tokens ?: array_filter(explode(' ', $ascii)));
    }

    /** @return list<string> tokens of 3+ characters, used for partial matching */
    public static function tokens(string $normalized): array
    {
        return array_values(array_filter(explode(' ', $normalized), fn ($t) => strlen($t) >= 3));
    }
}
