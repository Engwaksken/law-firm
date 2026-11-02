<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Contracts\HasRecordLevelAccess;
use App\Support\MatterAccess;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Work item. Visible to its assignee and creator, and (for matter tasks) to anyone who can see the matter. */
class Task extends Model implements HasRecordLevelAccess
{
    use Auditable, HasFactory, SoftDeletes;

    public const STATUSES = ['open' => 'Open', 'in_progress' => 'In progress', 'blocked' => 'Blocked', 'done' => 'Done'];

    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

    /** Mirrors database defaults so new instances behave correctly before being re-read. */
    protected $attributes = ['status' => 'open', 'priority' => 'normal'];

    protected $fillable = ['matter_id', 'title', 'description', 'assigned_to', 'due_at', 'priority', 'status'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime', 'escalated_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $t) => $t->created_by ??= auth()->id());
        static::saving(function (self $t) {
            if ($t->isDirty('status')) {
                $t->completed_at = $t->status === 'done' ? ($t->completed_at ?? now()) : null;
            }
        });
    }

    public function matter(): BelongsTo { return $this->belongsTo(Matter::class)->withTrashed(); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function comments(): HasMany { return $this->hasMany(TaskComment::class)->oldest(); }
    public function documents(): HasMany { return $this->hasMany(Document::class); }

    public function isOverdue(): bool { return $this->status !== 'done' && $this->due_at?->isPast(); }

    public function canBeSeenBy(User $user): bool
    {
        return $this->assigned_to === $user->id || $this->created_by === $user->id
            || ($this->matter !== null && MatterAccess::canView($user, $this->matter));
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('tasks.assigned_to', $user->id)
            ->orWhere('tasks.created_by', $user->id)
            ->orWhere(fn (Builder $m) => $m->whereNotNull('tasks.matter_id')
                ->whereIn('tasks.matter_id', MatterAccess::scope(Matter::query()->withTrashed(), $user)->select('matters.id'))));
    }

    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('status', '!=', 'done')->whereNotNull('due_at')->where('due_at', '<', now());
    }
}
