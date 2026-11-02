<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToMatter;
use App\Contracts\HasRecordLevelAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A case update written for the client. Visible in the portal only once approved/published (spec §13). */
class ClientUpdate extends Model implements HasRecordLevelAccess
{
    use Auditable, BelongsToMatter, HasFactory, SoftDeletes;

    public const STATUSES = ['draft' => 'Draft', 'pending_approval' => 'Pending approval', 'published' => 'Published'];

    protected $attributes = ['status' => 'draft'];

    protected $fillable = ['matter_id', 'title', 'body'];

    protected array $auditExclude = ['body'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $u) => $u->created_by ??= auth()->id());
    }

    public function author(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
