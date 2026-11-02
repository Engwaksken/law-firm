<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\FinanciallyVisible;
use App\Contracts\HasRecordLevelAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model implements HasRecordLevelAccess
{
    use Auditable, FinanciallyVisible, HasFactory, SoftDeletes;

    public const CATEGORIES = [
        'court_fees' => 'Court fees', 'filing' => 'Filing / registration', 'travel' => 'Travel', 'courier' => 'Courier / postage',
        'printing' => 'Printing / copying', 'expert' => 'Expert / third-party fees', 'search' => 'Searches', 'other' => 'Other',
    ];

    public const STATUSES = ['pending' => 'Pending approval', 'approved' => 'Approved', 'rejected' => 'Rejected', 'billed' => 'Billed'];

    protected $attributes = ['billable' => true, 'status' => 'pending', 'currency' => 'UGX'];

    protected $fillable = ['matter_id', 'user_id', 'incurred_on', 'category', 'description', 'amount', 'currency', 'billable', 'receipt_document_id'];

    protected function casts(): array
    {
        return ['incurred_on' => 'date', 'amount' => 'decimal:2', 'billable' => 'boolean', 'approved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $e) => $e->user_id ??= auth()->id());
        static::updating(fn (self $e) => in_array($e->getOriginal('status'), ['approved', 'billed'], true) && $e->isDirty(['amount', 'matter_id', 'billable', 'currency']) ? false : null);
    }

    public function matter(): BelongsTo { return $this->belongsTo(Matter::class)->withTrashed(); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function receipt(): BelongsTo { return $this->belongsTo(Document::class, 'receipt_document_id'); }
}
