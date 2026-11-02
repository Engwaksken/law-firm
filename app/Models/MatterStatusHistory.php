<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only record of every matter status change (spec §8). */
class MatterStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'matter_status_history';

    protected $fillable = ['matter_id', 'from_status', 'to_status', 'reason', 'user_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function matter(): BelongsTo { return $this->belongsTo(Matter::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
