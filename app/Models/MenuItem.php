<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

class MenuItem extends Model
{
    use HasFactory;

    public const LINK_TYPES = ['url' => 'Custom URL', 'page' => 'CMS page', 'route' => 'Built-in page'];

    /** Built-in public pages a menu item can point to. */
    public const ROUTES = [
        'home' => 'Home', 'about' => 'About', 'practice-areas.index' => 'Practice areas',
        'advocates.index' => 'Our advocates', 'insights.index' => 'Insights', 'events.index' => 'Events',
        'careers.index' => 'Careers', 'contact' => 'Contact', 'consultation' => 'Book a consultation',
        'privacy' => 'Privacy notice',
    ];

    protected $fillable = ['menu_id', 'parent_id', 'label', 'link_type', 'url', 'route_name', 'page_id', 'sort_order', 'is_active', 'open_in_new_tab'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'open_in_new_tab' => 'boolean'];
    }

    public function menu(): BelongsTo { return $this->belongsTo(Menu::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order'); }
    public function page(): BelongsTo { return $this->belongsTo(Page::class); }

    /** Resolved href, or null when the target is unavailable (unpublished page, unknown route). */
    public function href(): ?string
    {
        return match ($this->link_type) {
            'page' => $this->page?->isPublished() ? $this->page->url() : null,
            'route' => $this->route_name && array_key_exists($this->route_name, self::ROUTES) && Route::has($this->route_name)
                ? route($this->route_name) : null,
            default => \App\Support\SafeUrl::make($this->url),
        };
    }
}
