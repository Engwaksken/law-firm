<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * CMS page built from ordered blocks (page_blocks). Public URL is /{slug}.
 *
 * Workflow: draft → in_review → published → archived. A published page with
 * a future published_at is "scheduled". Every publish stores a revision.
 */
class Page extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const STATUSES = ['draft' => 'Draft', 'in_review' => 'In review', 'published' => 'Published', 'archived' => 'Archived'];

    public const TEMPLATES = ['default' => 'Standard (page header + content)', 'full-width' => 'Full width (blocks only)'];

    /** First URL segments owned by the application; a page slug may not use them. */
    public const RESERVED_SLUGS = [
        'admin', 'about', 'privacy', 'contact', 'book-a-consultation', 'practice-areas', 'advocates',
        'insights', 'careers', 'events', 'newsletter', 'sitemap.xml', 'robots.txt', 'preview', 'storage',
        'build', 'livewire', 'up', 'filament', 'css', 'js', 'fonts', 'portal', 'api', 'login', 'logout',
    ];

    protected $fillable = [
        'title', 'slug', 'template', 'excerpt', 'status', 'meta_title', 'meta_description', 'og_image_id',
        'noindex', 'show_in_sitemap', 'published_at', 'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime', 'submitted_at' => 'datetime',
            'noindex' => 'boolean', 'show_in_sitemap' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $page) {
            $page->created_by ??= auth()->id();
        });
        static::saving(function (self $page) {
            if (auth()->check()) {
                $page->updated_by = auth()->id();
            }
        });
    }

    public function blocks(): HasMany { return $this->hasMany(PageBlock::class)->orderBy('sort_order'); }
    public function activeBlocks(): HasMany { return $this->blocks()->where('is_active', true); }
    public function revisions(): HasMany { return $this->hasMany(PageRevision::class)->latest('created_at')->latest('id'); }
    public function menuItems(): HasMany { return $this->hasMany(MenuItem::class); }
    public function ogImage(): BelongsTo { return $this->belongsTo(Media::class, 'og_image_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function publisher(): BelongsTo { return $this->belongsTo(User::class, 'published_by'); }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->isPast();
    }

    public function isScheduled(): bool
    {
        return $this->status === 'published' && $this->published_at?->isFuture();
    }

    public function displayStatus(): string
    {
        return match (true) {
            $this->isScheduled() => 'Scheduled',
            $this->status === 'published' && $this->submitted_at !== null => 'Published · changes in review',
            default => self::STATUSES[$this->status] ?? $this->status,
        };
    }

    /** A draft submitted for review, or a live page whose pending edits were submitted. */
    public function isAwaitingReview(): bool
    {
        return $this->status === 'in_review' || ($this->status === 'published' && $this->submitted_at !== null);
    }

    public function url(): string { return url($this->slug); }

    public static function isReservedSlug(string $slug): bool
    {
        return in_array(strtolower(explode('/', trim($slug, '/'))[0]), self::RESERVED_SLUGS, true);
    }

    /**
     * Replace the page's blocks with the given ordered list.
     *
     * @param  list<array{type: string, data: array, is_active?: bool}>  $blocks
     */
    public function syncBlocks(array $blocks): void
    {
        DB::transaction(function () use ($blocks) {
            $this->blocks()->delete();
            foreach (array_values($blocks) as $i => $block) {
                $this->blocks()->create([
                    'type' => $block['type'],
                    'content' => $block['data'] ?? [],
                    'is_active' => $block['is_active'] ?? ($block['data']['is_active'] ?? true),
                    'sort_order' => $i,
                ]);
            }
        });
        $this->unsetRelation('blocks');
    }

    /** Blocks in Filament Builder state shape. */
    public function builderState(): array
    {
        return $this->blocks->map(fn (PageBlock $b) => ['type' => $b->type, 'data' => $b->content + ['is_active' => $b->is_active]])->all();
    }

    public function snapshot(): array
    {
        return [
            // Normalised so snapshots compare reliably and restore never writes NULL into NOT NULL columns.
            'page' => [
                'title' => $this->title,
                'slug' => $this->slug,
                'template' => $this->template ?? 'default',
                'excerpt' => $this->excerpt,
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
                'og_image_id' => $this->og_image_id,
                'noindex' => (bool) ($this->noindex ?? false),
                'show_in_sitemap' => (bool) ($this->show_in_sitemap ?? true),
            ],
            'blocks' => $this->blocks()->get()->map(fn (PageBlock $b) => ['type' => $b->type, 'data' => $b->content, 'is_active' => $b->is_active])->all(),
        ];
    }

    /** The revision currently live on the public site (latest publish), if any. */
    public function liveRevision(): ?PageRevision
    {
        return $this->revisions()->where('reason', 'published')->first();
    }

    /**
     * What the public sees. Edits to the working copy don't go live until the
     * next publish. Pages without a publish revision (e.g. seeded) fall back to
     * the working copy.
     *
     * @return array{page: array<string, mixed>, blocks: list<array{type: string, data: array, is_active: bool}>}
     */
    public function liveContent(): array
    {
        return $this->liveRevision()?->snapshot ?? $this->snapshot();
    }

    public function hasUnpublishedChanges(): bool
    {
        $live = $this->liveRevision();

        return $live !== null && json_encode($live->snapshot) !== json_encode($this->snapshot());
    }

    public function recordRevision(string $reason): PageRevision
    {
        return $this->revisions()->create(['user_id' => auth()->id(), 'reason' => $reason, 'snapshot' => $this->snapshot()]);
    }

    /** Restore content from a revision into the working copy; status is left unchanged. */
    public function restoreRevision(PageRevision $revision): void
    {
        abort_unless($revision->page_id === $this->id, 404);

        DB::transaction(function () use ($revision) {
            $this->fill(collect($revision->snapshot['page'] ?? [])->except('slug')->all())->save();
            $this->syncBlocks($revision->snapshot['blocks'] ?? []);
            $this->recordRevision('restored');
        });
    }
}
