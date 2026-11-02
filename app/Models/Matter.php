<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasReference;
use App\Contracts\HasRecordLevelAccess;
use App\Support\MatterAccess;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A legal matter / case. Status changes go through App\Legal\MatterLifecycle,
 * never by setting `status` directly, so every change is validated and recorded.
 */
class Matter extends Model implements HasRecordLevelAccess
{
    use Auditable, HasFactory, HasReference, SoftDeletes;

    public const REFERENCE_PREFIX = 'MAT';

    public const STATUSES = [
        'intake' => 'Intake',
        'conflict_review' => 'Conflict review',
        'awaiting_engagement' => 'Engagement approval',
        'active' => 'Active',
        'pending' => 'Pending',
        'stayed' => 'Stayed',
        'closed' => 'Closed',
        'archived' => 'Archived',
    ];

    /** Statuses in which the firm is acting and work can be recorded. */
    public const OPEN_STATUSES = ['active', 'pending', 'stayed'];

    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

    public const ASSIGNMENT_ROLES = ['lead' => 'Lead advocate', 'associate' => 'Associate', 'assistant' => 'Legal assistant', 'paralegal' => 'Paralegal', 'support' => 'Support'];

    /** Mirrors database defaults so new instances behave correctly before being re-read. */
    protected $attributes = ['status' => 'intake', 'priority' => 'normal', 'is_restricted' => false];

    protected $fillable = [
        'title', 'client_id', 'practice_area_id', 'case_type', 'court_id', 'jurisdiction', 'case_number', 'filing_date',
        'lead_user_id', 'office_id', 'priority', 'is_restricted', 'description',
    ];

    /** The description can hold privileged facts. */
    protected array $auditExclude = ['description'];

    protected function casts(): array
    {
        return [
            'filing_date' => 'date', 'opened_at' => 'datetime', 'closed_at' => 'datetime',
            'is_restricted' => 'boolean', 'description' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->created_by ??= auth()->id());
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class)->withTrashed(); }
    public function practiceArea(): BelongsTo { return $this->belongsTo(PracticeArea::class); }
    public function court(): BelongsTo { return $this->belongsTo(Court::class); }
    public function lead(): BelongsTo { return $this->belongsTo(User::class, 'lead_user_id'); }
    public function office(): BelongsTo { return $this->belongsTo(Office::class); }
    public function assignments(): HasMany { return $this->hasMany(MatterAssignment::class); }
    public function team(): BelongsToMany { return $this->belongsToMany(User::class, 'matter_assignments')->withPivot('role')->withTimestamps(); }
    public function parties(): HasMany { return $this->hasMany(MatterParty::class); }
    public function statusHistory(): HasMany { return $this->hasMany(MatterStatusHistory::class)->latest('created_at')->latest('id'); }
    public function conflictChecks(): HasMany { return $this->hasMany(ConflictCheck::class); }
    public function engagements(): HasMany { return $this->hasMany(Engagement::class); }
    public function approvedEngagement(): HasOne { return $this->hasOne(Engagement::class)->where('status', 'approved')->latestOfMany('approved_at'); }
    public function notes(): HasMany { return $this->hasMany(CaseNote::class); }
    public function hearings(): HasMany { return $this->hasMany(Hearing::class); }
    public function deadlines(): HasMany { return $this->hasMany(Deadline::class); }
    public function appointments(): HasMany { return $this->hasMany(Appointment::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
    public function documents(): HasMany { return $this->hasMany(Document::class); }

    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        MatterAccess::scope($query, $user);
    }

    public function isOpen(): bool { return in_array($this->status, self::OPEN_STATUSES, true); }

    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }

    public function label(): string { return "{$this->reference} · {$this->title}"; }
}
