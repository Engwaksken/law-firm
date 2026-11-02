<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToMatter;
use App\Contracts\HasRecordLevelAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hearing extends Model implements HasRecordLevelAccess
{
    use Auditable, BelongsToMatter, HasFactory, SoftDeletes;

    public const TYPES = ['hearing' => 'Hearing', 'mention' => 'Mention', 'ruling' => 'Ruling', 'judgment' => 'Judgment', 'trial' => 'Trial', 'other' => 'Other'];

    public const STATUSES = ['scheduled' => 'Scheduled', 'adjourned' => 'Adjourned', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    /** Mirrors database defaults so new instances behave correctly before being re-read. */
    protected $attributes = ['status' => 'scheduled', 'type' => 'hearing'];

    protected $fillable = ['matter_id', 'court_id', 'type', 'title', 'starts_at', 'ends_at', 'courtroom', 'judicial_officer', 'status', 'outcome', 'reminder_days'];

    protected array $auditExclude = ['outcome'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'outcome' => 'encrypted', 'reminder_days' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $h) => $h->created_by ??= auth()->id());
    }

    public function court(): BelongsTo { return $this->belongsTo(Court::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
