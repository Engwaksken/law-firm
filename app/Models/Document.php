<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Contracts\HasRecordLevelAccess;
use App\Support\MatterAccess;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Confidential legal document. Files are stored ONLY on the private disk
 * (config firm.documents.disk) and reached through signed, expiring,
 * authorised download links (spec §10).
 *
 * Access: matter documents follow the matter; client-level documents (no matter)
 * need clients.view; templates need documents.upload.
 */
class Document extends Model implements HasRecordLevelAccess
{
    use Auditable, HasFactory, SoftDeletes;

    public const CATEGORIES = [
        'pleading' => 'Pleading', 'affidavit' => 'Affidavit', 'agreement' => 'Agreement', 'contract' => 'Contract',
        'opinion' => 'Legal opinion', 'correspondence' => 'Correspondence', 'court_order' => 'Court order',
        'evidence' => 'Evidence', 'engagement' => 'Engagement / retainer', 'identification' => 'Identification',
        'template' => 'Template', 'other' => 'Other',
    ];

    public const STATUSES = ['draft' => 'Draft', 'pending_approval' => 'Pending approval', 'approved' => 'Approved'];

    /** Mirrors database defaults so new instances behave correctly before being re-read. */
    protected $attributes = ['status' => 'draft', 'category' => 'other', 'is_evidence' => false, 'is_template' => false];

    protected $fillable = ['matter_id', 'client_id', 'task_id', 'folder', 'category', 'title', 'description', 'is_evidence', 'is_template'];

    protected array $auditExclude = ['description'];

    protected function casts(): array
    {
        return ['is_evidence' => 'boolean', 'is_template' => 'boolean', 'approved_at' => 'datetime', 'description' => 'encrypted'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $d) => $d->uploaded_by ??= auth()->id());
    }

    public function matter(): BelongsTo { return $this->belongsTo(Matter::class)->withTrashed(); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class)->withTrashed(); }
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function versions(): HasMany { return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number'); }
    public function currentVersion(): BelongsTo { return $this->belongsTo(DocumentVersion::class, 'current_version_id'); }
    public function accessLogs(): HasMany { return $this->hasMany(DocumentAccessLog::class)->latest('created_at'); }

    public function canBeSeenBy(User $user): bool
    {
        if ($this->matter_id !== null) {
            return $this->matter !== null && MatterAccess::canView($user, $this->matter);
        }
        if ($this->is_template) {
            return $user->can('documents.upload');
        }

        return $user->can('clients.view');
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $q) use ($user) {
            $q->whereIn('documents.matter_id', MatterAccess::scope(Matter::query()->withTrashed(), $user)->select('matters.id'));
            if ($user->can('clients.view')) {
                $q->orWhere(fn (Builder $c) => $c->whereNull('documents.matter_id')->where('documents.is_template', false));
            }
            if ($user->can('documents.upload')) {
                $q->orWhere(fn (Builder $t) => $t->whereNull('documents.matter_id')->where('documents.is_template', true));
            }
        });
    }
}
