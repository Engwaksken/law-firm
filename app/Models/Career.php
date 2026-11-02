<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Career extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const TYPES = ['job' => 'Vacancy', 'internship' => 'Internship', 'pupillage' => 'Pupillage'];

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'closed' => 'Closed'];

    protected $fillable = [
        'office_id', 'department_id', 'title', 'slug', 'type', 'employment_type', 'location',
        'summary', 'description', 'requirements', 'status', 'published_at', 'closes_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'closes_at' => 'date'];
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function office(): BelongsTo { return $this->belongsTo(Office::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function applications(): HasMany { return $this->hasMany(CareerApplication::class); }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /** Published and the closing date (inclusive) hasn't passed. */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->published()->where(fn ($q) => $q->whereNull('closes_at')->orWhereDate('closes_at', '>=', today()));
    }

    public function isOpen(): bool
    {
        return $this->status === 'published' && $this->published_at?->isPast()
            && ($this->closes_at === null || $this->closes_at->endOfDay()->isFuture());
    }

    public function typeLabel(): string { return self::TYPES[$this->type] ?? ucfirst($this->type); }
}
