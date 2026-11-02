<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Public website media library. Files live on the PUBLIC disk and are meant
 * for publication. Never use it for client or case documents (Phase 4 has a private store).
 */
class Media extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'uploaded_by', 'disk', 'path', 'original_name', 'title', 'mime_type', 'size_bytes',
        'width', 'height', 'alt_text', 'caption', 'collection',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    protected static function booted(): void
    {
        static::forceDeleted(fn (self $media) => Storage::disk($media->disk)->delete($media->path));
    }

    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function url(): string { return Storage::disk($this->disk)->url($this->path); }

    public function isImage(): bool { return str_starts_with((string) $this->mime_type, 'image/'); }

    /** Label for pickers: title or file name, plus dimensions. */
    public function label(): string
    {
        return ($this->title ?: $this->original_name).($this->width ? " ({$this->width}×{$this->height})" : '');
    }
}
