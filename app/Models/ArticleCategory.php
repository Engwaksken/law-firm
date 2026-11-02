<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArticleCategory extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'description'];

    public function getRouteKeyName(): string { return 'slug'; }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_category', 'article_category_id', 'article_id');
    }
}
