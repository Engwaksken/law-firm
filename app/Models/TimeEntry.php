<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\FinanciallyVisible;
use App\Contracts\HasRecordLevelAccess;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeEntry extends Model implements HasRecordLevelAccess
{
    use Auditable, FinanciallyVisible, HasFactory, SoftDeletes;

    public const STATUSES = ['unbilled' => 'Unbilled', 'billed' => 'Billed', 'written_off' => 'Written off'];

    protected $attributes = ['billable' => true, 'status' => 'unbilled', 'currency' => 'UGX', 'rate' => '0.00', 'amount' => '0.00'];

    protected $fillable = ['matter_id', 'user_id', 'worked_on', 'minutes', 'description', 'billable', 'rate', 'currency'];

    protected function casts(): array
    {
        return ['worked_on' => 'date', 'minutes' => 'integer', 'billable' => 'boolean', 'rate' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $t) => $t->user_id ??= auth()->id());
        // Amount is always derived, never typed: minutes × hourly rate (0 when non-billable).
        static::saving(function (self $t) {
            $t->amount = $t->billable ? Money::forMinutes((int) $t->minutes, $t->rate) : '0.00';
        });
        // Billed time is locked: it is part of an issued (or draft) invoice.
        static::updating(fn (self $t) => $t->getOriginal('status') === 'billed' && $t->isDirty(['minutes', 'rate', 'billable', 'matter_id', 'description']) ? false : null);
    }

    public function matter(): BelongsTo { return $this->belongsTo(Matter::class)->withTrashed(); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function hours(): string { return number_format($this->minutes / 60, 2); }
}
