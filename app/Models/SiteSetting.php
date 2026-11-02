<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use Auditable, HasFactory;

    protected $table = 'site_settings';

    protected $fillable = ['key', 'value', 'type'];

    protected static function booted(): void
    {
        static::saved(fn () => Settings::flush());
        static::deleted(fn () => Settings::flush());
    }
}
