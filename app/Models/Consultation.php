<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasReference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A consultation REQUEST from the website. It does not create an advocate–client
 * relationship; engagement happens later through intake and conflict checks.
 */
class Consultation extends Model
{
    use Auditable, HasFactory, HasReference, SoftDeletes;

    public const REFERENCE_PREFIX = 'CON';

    public const TYPES = ['in_person' => 'In person', 'phone' => 'Phone call', 'video' => 'Video call'];

    public const STATUSES = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'declined' => 'Declined', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    protected $fillable = [
        'client_id', 'advocate_id', 'practice_area_id', 'name', 'email', 'phone', 'message',
        'consultation_type', 'preferred_date', 'preferred_time', 'scheduled_at', 'status',
        'internal_notes', 'privacy_accepted_at',
    ];

    /** The visitor's message may contain confidential facts: keep it out of the audit trail. */
    protected array $auditExclude = ['message', 'internal_notes'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'preferred_date' => 'date', 'privacy_accepted_at' => 'datetime'];
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function advocate(): BelongsTo { return $this->belongsTo(Advocate::class); }
    public function practiceArea(): BelongsTo { return $this->belongsTo(PracticeArea::class); }
}
