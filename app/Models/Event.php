<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'cancelled' => 'Cancelled'];

    protected $fillable = [
        'title', 'slug', 'summary', 'description', 'image_id', 'starts_at', 'ends_at', 'location',
        'is_online', 'registration_url', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'published_at' => 'datetime', 'is_online' => 'boolean'];
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function image(): BelongsTo { return $this->belongsTo(Media::class, 'image_id'); }

    /** Published (or cancelled, so attendees see the notice) and due. */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->whereIn('status', ['published', 'cancelled'])->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->visible()->where(fn ($q) => $q->where('ends_at', '>=', now())->orWhere(fn ($q) => $q->whereNull('ends_at')->where('starts_at', '>=', now()->startOfDay())))
            ->orderBy('starts_at');
    }

    public function isPast(): bool { return ($this->ends_at ?? $this->starts_at)->isPast(); }
}
