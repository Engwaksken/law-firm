<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PracticeArea extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'summary', 'description', 'body', 'icon', 'image_path', 'faqs',
        'is_active', 'is_featured', 'sort_order', 'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_featured' => 'boolean', 'faqs' => 'array'];
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function advocates(): BelongsToMany { return $this->belongsToMany(Advocate::class); }
    public function consultations(): HasMany { return $this->hasMany(Consultation::class); }

    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
