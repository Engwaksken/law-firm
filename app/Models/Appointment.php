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
use Illuminate\Database\Eloquent\SoftDeletes;

/** Meetings. May belong to a matter (access follows it) or stand alone (organiser and attendees only). */
class Appointment extends Model implements HasRecordLevelAccess
{
    use Auditable, HasFactory, SoftDeletes;

    public const TYPES = ['client_meeting' => 'Client meeting', 'internal' => 'Internal meeting', 'consultation' => 'Consultation', 'other' => 'Other'];

    /** Mirrors database defaults so new instances behave correctly before being re-read. */
    protected $attributes = ['status' => 'scheduled', 'type' => 'client_meeting'];

    protected $fillable = ['matter_id', 'client_id', 'title', 'type', 'starts_at', 'ends_at', 'location', 'organizer_id', 'attendee_ids', 'notes', 'reminder_days', 'status'];

    protected array $auditExclude = ['notes'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'attendee_ids' => 'array', 'reminder_days' => 'array', 'notes' => 'encrypted'];
    }

    public function matter(): BelongsTo { return $this->belongsTo(Matter::class)->withTrashed(); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class)->withTrashed(); }
    public function organizer(): BelongsTo { return $this->belongsTo(User::class, 'organizer_id'); }

    public function involves(User $user): bool
    {
        return $this->organizer_id === $user->id || in_array($user->id, array_map('intval', $this->attendee_ids ?? []), true);
    }

    public function canBeSeenBy(User $user): bool
    {
        return $this->involves($user) || ($this->matter !== null && MatterAccess::canView($user, $this->matter));
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('organizer_id', $user->id)
            ->orWhereJsonContains('attendee_ids', $user->id)
            ->orWhereJsonContains('attendee_ids', (string) $user->id)
            ->orWhere(fn (Builder $m) => $m->whereNotNull('matter_id')->whereIn('matter_id', MatterAccess::scope(Matter::query()->withTrashed(), $user)->select('matters.id'))));
    }

    /** Everyone to remind: organiser + attendees. */
    public function participants()
    {
        return User::query()->whereIn('id', array_filter(array_merge([$this->organizer_id], array_map('intval', $this->attendee_ids ?? []))))->get();
    }
}
