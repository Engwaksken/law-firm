<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComment extends Model
{
    protected $fillable = ['task_id', 'body'];

    protected static function booted(): void
    {
        static::creating(fn (self $c) => $c->user_id ??= auth()->id());
    }

    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
