<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNote extends Model
{
    use Auditable;

    protected $fillable = ['number', 'invoice_id', 'amount', 'reason', 'issued_by', 'issued_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'issued_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function issuer(): BelongsTo { return $this->belongsTo(User::class, 'issued_by'); }
}
