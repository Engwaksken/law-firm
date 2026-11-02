<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only chain-of-custody log for documents. */
class DocumentAccessLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['document_id', 'document_version_id', 'user_id', 'action', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public static function record(Document $document, string $action, ?DocumentVersion $version = null): self
    {
        return static::create([
            'document_id' => $document->id,
            'document_version_id' => $version?->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 255) ?: null,
        ]);
    }

    public function document(): BelongsTo { return $this->belongsTo(Document::class)->withTrashed(); }
    public function version(): BelongsTo { return $this->belongsTo(DocumentVersion::class, 'document_version_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
