<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Secure message between the firm and a client. Bodies are encrypted at rest
 * and never sent by email (emails only say a new message is waiting).
 */
class ClientMessage extends Model
{
    use HasFactory;

    public const CATEGORIES = ['message' => 'Message', 'support' => 'Support request'];

    protected $attributes = ['category' => 'message'];

    protected $fillable = ['client_id', 'matter_id', 'parent_id', 'subject', 'body', 'category'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'from_client' => 'boolean', 'read_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->sender_id ??= auth()->id());
        // Messages are a record of communication: they can be marked read, nothing else.
        static::updating(fn (self $m) => collect($m->getDirty())->keys()->diff(['read_at'])->isEmpty() ? null : false);
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class)->withTrashed(); }
    public function matter(): BelongsTo { return $this->belongsTo(Matter::class)->withTrashed(); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function replies(): HasMany { return $this->hasMany(self::class, 'parent_id')->oldest(); }
}
