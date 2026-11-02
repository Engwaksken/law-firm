<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceItem extends Model
{
    public const TYPES = ['time' => 'Professional fees (time)', 'expense' => 'Disbursement', 'fee' => 'Professional fee', 'other' => 'Other'];

    protected $attributes = ['type' => 'fee', 'quantity' => '1.00', 'tax_rate' => '0.00'];

    protected $fillable = ['invoice_id', 'type', 'description', 'quantity', 'unit_price', 'tax_rate', 'sort_order'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'tax_rate' => 'decimal:2',
            'line_subtotal' => 'decimal:2', 'line_tax' => 'decimal:2', 'line_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Line totals are derived (quantity × unit price, then tax on the rounded subtotal).
        static::saving(function (self $item) {
            $item->line_subtotal = Money::mul($item->quantity, $item->unit_price);
            $item->line_tax = Money::percent($item->line_subtotal, $item->tax_rate);
            $item->line_total = Money::add($item->line_subtotal, $item->line_tax);
        });
        // Lines of an issued invoice are immutable.
        $locked = fn (self $item) => $item->invoice && ! $item->invoice->isEditable() ? false : null;
        static::updating($locked);
        static::deleting($locked);
    }

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function timeEntries(): HasMany { return $this->hasMany(TimeEntry::class); }
    public function expenses(): HasMany { return $this->hasMany(Expense::class); }
}
