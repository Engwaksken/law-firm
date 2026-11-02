<?php

namespace App\Concerns;

use Illuminate\Support\Str;

/**
 * Assigns a human-friendly unique reference (e.g. CON-2611-7KQ4ZP) on creation.
 * Random rather than sequential, so references don't reveal volumes or allow guessing.
 */
trait HasReference
{
    public static function bootHasReference(): void
    {
        static::creating(function ($model) {
            if (blank($model->reference)) {
                do {
                    $reference = static::REFERENCE_PREFIX.'-'.now()->format('ym').'-'.Str::upper(Str::random(6));
                } while ((method_exists(static::class, 'bootSoftDeletes') ? static::withTrashed() : static::query())->where('reference', $reference)->exists());
                $model->reference = $reference;
            }
        });
    }
}
