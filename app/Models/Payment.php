<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\FinanciallyVisible;
use App\Contracts\HasRecordLevelAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Firm revenue received against an invoice. Payments are reversed, never deleted or edited. */
class Payment extends Model implements HasRecordLevelAccess
{
    use Auditable, FinanciallyVisible, HasFactory;

    public const METHODS = ['bank_transfer' => 'Bank transfer', 'mobile_money' => 'Mobile money', 'cash' => 'Cash', 'cheque' => 'Cheque', 'card' => 'Card', 'other' => 'Other'];

    protected $fillable = ['invoice_id', 'client_id', 'amount', 'currency', 'method', 'reference', 'received_on', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'received_on' => 'date', 'reversed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $p) => $p->recorded_by ??= auth()->id());
        static::updating(fn (self $p) => collect($p->getDirty())->keys()->diff(['reversed_at', 'reversed_by', 'reversal_reason'])->isEmpty() ? null : false);
        static::deleting(fn () => false);
    }

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class)->withTrashed(); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function receipt(): HasOne { return $this->hasOne(Receipt::class); }

    /** Matter, through the invoice (for visibility checks). */
    public function getMatterAttribute(): ?Matter { return $this->invoice?->matter; }

    public function getMatterIdAttribute(): ?int { return $this->invoice?->matter_id; }

    public function isReversed(): bool { return $this->reversed_at !== null; }

    /** Payments have no matter_id: visibility follows the invoice. */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function visibleTo(\Illuminate\Database\Eloquent\Builder $query, User $user): void
    {
        if (! static::userSeesAllFinance($user)) {
            $query->whereIn('payments.invoice_id', Invoice::query()->visibleTo($user)->select('invoices.id'));
        }
    }
}
