<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Double opt-in: a subscriber counts only after confirming by email. */
class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected $fillable = ['email', 'name', 'token', 'confirmed_at', 'unsubscribed_at'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $s) => $s->token ??= Str::random(64));
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNotNull('confirmed_at')->whereNull('unsubscribed_at');
    }
}
