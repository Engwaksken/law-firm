<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\FinanciallyVisible;
use App\Contracts\HasRecordLevelAccess;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Totals are always recalculated by App\Finance\InvoiceService. Once issued an
 * invoice is immutable: corrections are made with credit notes, never edits.
 */
class Invoice extends Model implements HasRecordLevelAccess
{
    use Auditable, FinanciallyVisible, HasFactory;

    public const STATUSES = [
        'draft' => 'Draft', 'pending_approval' => 'Pending approval', 'issued' => 'Issued',
        'partially_paid' => 'Partially paid', 'paid' => 'Paid', 'void' => 'Void',
    ];

    /** Statuses that represent money owed (or settled) by the client. */
    public const ISSUED_STATUSES = ['issued', 'partially_paid', 'paid'];

    protected $attributes = [
        'status' => 'draft', 'currency' => 'UGX', 'subtotal' => '0.00', 'tax_total' => '0.00', 'total' => '0.00',
        'amount_paid' => '0.00', 'amount_credited' => '0.00', 'balance' => '0.00',
    ];

    protected $fillable = ['matter_id', 'client_id', 'currency', 'due_date', 'notes', 'internal_notes'];

    protected array $auditExclude = ['internal_notes'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date', 'due_date' => 'date', 'issued_at' => 'datetime', 'voided_at' => 'datetime',
            'subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2',
            'amount_paid' => 'decimal:2', 'amount_credited' => 'decimal:2', 'balance' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $i) => $i->created_by ??= auth()->id());
        static::deleting(fn (self $i) => $i->status === 'draft' ? null : false);
    }

    public function matter(): BelongsTo { return $this->belongsTo(Matter::class)->withTrashed(); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class)->withTrashed(); }
    public function items(): HasMany { return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id'); }
    public function payments(): HasMany { return $this->hasMany(Payment::class)->latest('received_on'); }
    public function creditNotes(): HasMany { return $this->hasMany(CreditNote::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public function isEditable(): bool { return in_array($this->status, ['draft'], true); }

    public function isIssued(): bool { return in_array($this->status, self::ISSUED_STATUSES, true); }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['issued', 'partially_paid'], true) && $this->due_date?->isPast();
    }

    public function displayNumber(): string { return $this->number ?? 'DRAFT-'.$this->id; }

    #[Scope]
    protected function outstanding(Builder $query): void
    {
        $query->whereIn('status', ['issued', 'partially_paid'])->where('balance', '>', 0);
    }
}
