<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasReference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Result of a conflict search. The system only finds candidate matches. A
 * person with conflicts.review must decide (spec §7: human review required).
 */
class ConflictCheck extends Model
{
    use Auditable, HasFactory, HasReference;

    public const REFERENCE_PREFIX = 'CC';

    public const STATUSES = [
        'pending_review' => 'Pending review', 'cleared' => 'Cleared', 'conflict_found' => 'Conflict found', 'waived' => 'Conflict waived',
    ];

    /** Outcomes that allow an engagement to proceed. */
    public const PASSING = ['cleared', 'waived'];

    protected $fillable = ['client_id', 'matter_id', 'requested_by', 'status', 'results', 'review_notes'];

    protected function casts(): array
    {
        return ['results' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class)->withTrashed(); }
    public function matter(): BelongsTo { return $this->belongsTo(Matter::class)->withTrashed(); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function parties(): HasMany { return $this->hasMany(ConflictCheckParty::class); }

    public function hitCount(): int { return count($this->results['hits'] ?? []); }

    public function passed(): bool { return in_array($this->status, self::PASSING, true); }
}
