<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

/** Append-only audit record. */
class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'event', 'subject_type', 'subject_id', 'description', 'properties', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function subject(): MorphTo { return $this->morphTo(); }

    public static function record(string $event, ?Model $subject = null, ?string $description = null, array $properties = [], ?int $userId = null): self
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return static::create([
            'user_id' => $userId ?? Auth::id(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }
}
