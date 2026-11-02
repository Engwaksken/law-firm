<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToMatter;
use App\Contracts\HasRecordLevelAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A date entered by staff. The system never calculates statutory deadlines
 * (spec §9): reminders are notification preferences only.
 */
class Deadline extends Model implements HasRecordLevelAccess
{
    use Auditable, BelongsToMatter, HasFactory, SoftDeletes;

    public const TYPES = ['filing' => 'Filing deadline', 'submission' => 'Document submission', 'statutory' => 'Statutory deadline', 'internal' => 'Internal deadline', 'other' => 'Other'];

    /** Mirrors database defaults so new instances behave correctly before being re-read. */
    protected $attributes = ['type' => 'filing'];

    protected $fillable = ['matter_id', 'title', 'type', 'due_at', 'responsible_user_id', 'notes', 'reminder_days'];

    protected array $auditExclude = ['notes'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime', 'notes' => 'encrypted', 'reminder_days' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $d) => $d->created_by ??= auth()->id());
    }

    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_user_id'); }
    public function completer(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }

    public function isOverdue(): bool { return $this->completed_at === null && $this->due_at->isPast(); }
}
