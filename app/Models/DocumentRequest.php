<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToMatter;
use App\Contracts\HasRecordLevelAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The firm asks the client for a document; the client uploads it through the portal. */
class DocumentRequest extends Model implements HasRecordLevelAccess
{
    use Auditable, BelongsToMatter, HasFactory;

    public const STATUSES = ['open' => 'Requested', 'submitted' => 'Submitted', 'accepted' => 'Accepted', 'cancelled' => 'Cancelled'];

    protected $attributes = ['status' => 'open'];

    protected $fillable = ['matter_id', 'title', 'description', 'due_on'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'submitted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $r) => $r->requested_by ??= auth()->id());
    }

    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
}
