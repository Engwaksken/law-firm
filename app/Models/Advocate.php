<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Advocate extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'office_id', 'name', 'slug', 'title', 'bio', 'email', 'phone', 'photo_path',
        'qualifications', 'experience', 'memberships', 'is_public', 'accepts_consultations',
        'show_email', 'show_phone', 'is_featured', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'is_public' => 'boolean', 'is_featured' => 'boolean',
            'accepts_consultations' => 'boolean', 'show_email' => 'boolean', 'show_phone' => 'boolean',
            'qualifications' => 'array', 'memberships' => 'array',
            'credentials_verified_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function office(): BelongsTo { return $this->belongsTo(Office::class); }
    public function verifiedBy(): BelongsTo { return $this->belongsTo(User::class, 'credentials_verified_by'); }
    public function practiceAreas(): BelongsToMany { return $this->belongsToMany(PracticeArea::class); }
    public function consultations(): HasMany { return $this->hasMany(Consultation::class); }

    public function isVerified(): bool { return $this->credentials_verified_at !== null; }

    /** Website shows only active profiles the firm opted to publish AND whose credentials were verified. */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_active', true)->where('is_public', true)->whereNotNull('credentials_verified_at')
            ->orderBy('sort_order')->orderBy('name');
    }

    public function markVerified(User $by): void
    {
        $this->forceFill(['credentials_verified_at' => now(), 'credentials_verified_by' => $by->id])->save();
    }

    public function revokeVerification(): void
    {
        $this->forceFill(['credentials_verified_at' => null, 'credentials_verified_by' => null])->save();
    }
}
