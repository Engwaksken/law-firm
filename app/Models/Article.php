<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const TYPES = [
        'article' => 'Article',
        'legal-update' => 'Legal update',
        'commentary' => 'Commentary',
        'publication' => 'Publication',
        'newsletter' => 'Newsletter',
        'report' => 'Report',
        'announcement' => 'News & announcement',
    ];

    protected $fillable = [
        'author_id', 'title', 'slug', 'type', 'excerpt', 'body', 'status', 'featured_image', 'attachment_path',
        'is_featured', 'tags', 'meta_title', 'meta_description', 'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'is_featured' => 'boolean', 'tags' => 'array'];
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ArticleCategory::class, 'article_category', 'article_id', 'article_category_id');
    }

    /** Published and due: supports scheduled publication via a future published_at. */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->isPast();
    }

    public function typeLabel(): string { return self::TYPES[$this->type] ?? ucfirst($this->type); }

    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $this->body)) / 220));
    }
}
