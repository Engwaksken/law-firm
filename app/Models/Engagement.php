<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToMatter;
use App\Contracts\HasRecordLevelAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Engagement extends Model implements HasRecordLevelAccess
{
    use Auditable, BelongsToMatter, HasFactory;

    public const FEE_ARRANGEMENTS = [
        'hourly' => 'Hourly rate', 'fixed' => 'Fixed fee', 'retainer' => 'Retainer', 'contingency' => 'Contingency', 'pro_bono' => 'Pro bono',
    ];

    public const STATUSES = ['draft' => 'Draft', 'pending_approval' => 'Pending approval', 'approved' => 'Approved', 'declined' => 'Declined'];

    /** Mirrors database defaults so new instances behave correctly before being re-read. */
    protected $attributes = ['status' => 'draft', 'currency' => 'UGX'];

    protected $fillable = [
        'matter_id', 'client_id', 'conflict_check_id', 'fee_arrangement', 'hourly_rate', 'fixed_fee', 'retainer_amount',
        'currency', 'scope', 'terms', 'start_date',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2', 'fixed_fee' => 'decimal:2', 'retainer_amount' => 'decimal:2',
            'start_date' => 'date', 'approved_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class)->withTrashed(); }
    public function conflictCheck(): BelongsTo { return $this->belongsTo(ConflictCheck::class); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
