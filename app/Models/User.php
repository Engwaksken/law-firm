<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Support\Access;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    use Auditable, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'job_title', 'office_id', 'department_id',
        'email_verified_at', 'password', 'is_active', 'default_hourly_rate', 'notification_preferences',
    ];

    protected $hidden = [
        'password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes',
    ];

    /** Never write these values into the audit trail. */
    protected array $auditExclude = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes', 'calendar_feed_token_hash', 'last_login_at', 'last_login_ip'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'default_hourly_rate' => 'decimal:2',
            'notification_preferences' => 'array',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    public function office(): BelongsTo { return $this->belongsTo(Office::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function advocate(): HasOne { return $this->hasOne(Advocate::class); }
    public function client(): HasOne { return $this->hasOne(Client::class); }

    /** Clients this user represents in the portal (active links only). */
    public function portalClients(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_portal_users')->wherePivotNull('revoked_at')->withPivot(['invited_at', 'revoked_at']);
    }

    /** @return list<int> */
    public function portalClientIds(): array
    {
        return $this->portalClients()->pluck('clients.id')->all();
    }

    /** Preference for a notification channel/topic, default on. */
    public function wantsNotification(string $topic): bool
    {
        return (bool) (($this->notification_preferences ?? [])[$topic] ?? true);
    }

    /**
     * Staff panel (/admin): active users holding at least one non-client role.
     * Client portal (/portal): active users with the client role AND an active link to a client.
     * The two are kept apart: a staff account never doubles as a client login.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active || $this->trashed()) {
            return false;
        }

        $staffRoles = $this->roles->pluck('name')->diff([Access::CLIENT]);

        if ($panel->getId() === 'portal') {
            return $staffRoles->isEmpty() && $this->hasRole(Access::CLIENT) && $this->portalClients()->exists();
        }

        return $staffRoles->isNotEmpty();
    }

    public function isPortalUser(): bool
    {
        return $this->hasRole(Access::CLIENT) && $this->roles->count() === 1;
    }

    public function isUnrestricted(): bool
    {
        return $this->hasAnyRole(Access::UNRESTRICTED_ROLES);
    }

    public function requiresMultiFactorAuthentication(): bool
    {
        return $this->hasAnyRole(Access::MFA_REQUIRED_ROLES);
    }

    public function getAppAuthenticationSecret(): ?string { return $this->app_authentication_secret; }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string { return $this->email; }

    public function getAppAuthenticationRecoveryCodes(): ?array { return $this->app_authentication_recovery_codes; }

    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}
