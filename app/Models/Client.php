<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasReference;
use App\Support\NameNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use Auditable, HasFactory, HasReference, SoftDeletes;

    public const REFERENCE_PREFIX = 'CL';

    public const TYPES = ['individual' => 'Individual', 'corporate' => 'Corporate / organisation'];

    public const STATUSES = ['prospect' => 'Prospect', 'active' => 'Active', 'inactive' => 'Inactive', 'former' => 'Former client'];

    public const ID_TYPES = ['national_id' => 'National ID', 'passport' => 'Passport', 'driving_permit' => 'Driving permit', 'other' => 'Other'];

    /** Mirrors database defaults so new instances behave correctly before being re-read. */
    protected $attributes = ['status' => 'prospect', 'type' => 'individual', 'country' => 'Uganda'];

    protected $fillable = [
        'user_id', 'type', 'name', 'email', 'phone', 'company', 'registration_number', 'id_type', 'id_number',
        'address', 'city', 'country', 'status', 'category', 'responsible_user_id', 'office_id', 'intake_source', 'notes',
    ];

    /** Identification numbers and notes are encrypted at rest and never written to the audit trail. */
    protected array $auditExclude = ['id_number', 'notes'];

    protected function casts(): array
    {
        return ['id_number' => 'encrypted', 'notes' => 'encrypted'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $client) {
            $client->normalized_name = NameNormalizer::normalize($client->name);
        });
        static::creating(fn (self $client) => $client->created_by ??= auth()->id());
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function responsibleUser(): BelongsTo { return $this->belongsTo(User::class, 'responsible_user_id'); }
    public function office(): BelongsTo { return $this->belongsTo(Office::class); }
    public function contacts(): HasMany { return $this->hasMany(ClientContact::class); }
    public function matters(): HasMany { return $this->hasMany(Matter::class); }
    public function engagements(): HasMany { return $this->hasMany(Engagement::class); }
    public function conflictChecks(): HasMany { return $this->hasMany(ConflictCheck::class); }
    public function consultations(): HasMany { return $this->hasMany(Consultation::class); }
    public function documents(): HasMany { return $this->hasMany(Document::class); }
    public function invoices(): HasMany { return $this->hasMany(Invoice::class); }
    public function messages(): HasMany { return $this->hasMany(ClientMessage::class); }

    public function portalUsers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_portal_users')->withPivot(['invited_by', 'invited_at', 'revoked_at']);
    }

    /** Masked for display: only the last 4 characters of an ID number are shown. */
    public function maskedIdNumber(): ?string
    {
        return $this->id_number ? str_repeat('•', max(0, strlen($this->id_number) - 4)).substr($this->id_number, -4) : null;
    }
}
