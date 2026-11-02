<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasReference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class CareerApplication extends Model
{
    use Auditable, HasFactory, HasReference, SoftDeletes;

    public const REFERENCE_PREFIX = 'APP';

    /** CVs live on the private "local" disk. Never on "public". */
    public const CV_DISK = 'local';

    public const STATUSES = [
        'received' => 'Received',
        'under_review' => 'Under review',
        'shortlisted' => 'Shortlisted',
        'interview' => 'Invited to interview',
        'offered' => 'Offer made',
        'unsuccessful' => 'Unsuccessful',
        'withdrawn' => 'Withdrawn',
    ];

    /** Statuses that trigger an email to the applicant when set. */
    public const NOTIFY_ON = ['under_review', 'shortlisted', 'interview', 'offered', 'unsuccessful'];

    protected $fillable = [
        'career_id', 'name', 'email', 'phone', 'cover_letter', 'cv_path', 'cv_original_name',
        'status', 'internal_notes', 'reviewed_by', 'privacy_accepted_at',
    ];

    protected array $auditExclude = ['cover_letter', 'internal_notes', 'cv_path'];

    protected function casts(): array
    {
        return ['privacy_accepted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // Removing the record permanently also removes the CV file (data minimisation).
        static::forceDeleted(fn (self $application) => Storage::disk(self::CV_DISK)->delete($application->cv_path));
    }

    public function career(): BelongsTo { return $this->belongsTo(Career::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }
}
