<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToMatter;
use App\Contracts\HasRecordLevelAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Internal, privileged by default, encrypted at rest. Never shared with clients automatically (spec §13). */
class CaseNote extends Model implements HasRecordLevelAccess
{
    use Auditable, BelongsToMatter, HasFactory, SoftDeletes;

    /** Mirrors database defaults so new instances behave correctly before being re-read. */
    protected $attributes = ['is_privileged' => true];

    protected $fillable = ['matter_id', 'title', 'body', 'is_privileged'];

    protected array $auditExclude = ['body', 'title'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'is_privileged' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $n) => $n->user_id ??= auth()->id());
    }

    public function author(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
