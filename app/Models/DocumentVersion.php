<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable file version. sha256 lets anyone verify the file hasn't changed since upload. */
class DocumentVersion extends Model
{
    public const UPDATED_AT = null;

    public const SCAN_STATUSES = ['pending' => 'Scan pending', 'clean' => 'Clean', 'infected' => 'Infected (quarantined)', 'not_scanned' => 'Not scanned', 'error' => 'Scan error'];

    protected $fillable = ['document_id', 'version_number', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'scan_status', 'scanned_at', 'change_notes', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'version_number' => 'integer', 'scanned_at' => 'datetime', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $v) => $v->uploaded_by ??= auth()->id());
        // File content is immutable: only scan results may change after upload.
        static::updating(function (self $v) {
            return collect($v->getDirty())->keys()->diff(['scan_status', 'scanned_at'])->isEmpty();
        });
    }

    public function document(): BelongsTo { return $this->belongsTo(Document::class)->withTrashed(); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }

    /** Only clean files (or files on systems without a scanner) may be downloaded. */
    public function isDownloadable(): bool
    {
        return in_array($this->scan_status, ['clean', 'not_scanned'], true);
    }
}
