<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['page_id', 'user_id', 'reason', 'snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'created_at' => 'datetime'];
    }

    public function page(): BelongsTo { return $this->belongsTo(Page::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
