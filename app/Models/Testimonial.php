<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Testimonial extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = ['author_name', 'author_title', 'quote', 'consent_obtained', 'is_approved', 'approved_by', 'sort_order'];

    protected function casts(): array
    {
        return ['consent_obtained' => 'boolean', 'is_approved' => 'boolean'];
    }

    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    /** Shown only with documented client consent AND staff approval. */
    #[Scope]
    protected function publishable(Builder $query): void
    {
        $query->where('consent_obtained', true)->where('is_approved', true)->orderBy('sort_order');
    }
}
