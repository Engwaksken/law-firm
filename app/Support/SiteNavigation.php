<?php

namespace App\Support;

use App\Models\Menu;
use App\Models\PracticeArea;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Builds the public header navigation. A CMS menu at location "header" wins
 * when it has items (Phase 3 adds the menu builder); otherwise a sensible
 * default is used, with a practice-area mega menu.
 *
 * Each item: ['label' => string, 'url' => string, 'active' => string|null (route pattern), 'children' => list]
 */
class SiteNavigation
{
    /** @return list<array<string, mixed>> */
    public static function header(): array
    {
        return static::fromMenu('header') ?? [
            ['label' => 'About', 'url' => route('about'), 'active' => 'about'],
            ['label' => 'Practice areas', 'url' => route('practice-areas.index'), 'active' => 'practice-areas.*', 'mega' => true,
                'children' => static::practiceAreaLinks()],
            ['label' => 'Our advocates', 'url' => route('advocates.index'), 'active' => 'advocates.*'],
            ['label' => 'Insights', 'url' => route('insights.index'), 'active' => 'insights.*'],
            ['label' => 'Careers', 'url' => route('careers.index'), 'active' => 'careers.*'],
            ['label' => 'Contact', 'url' => route('contact'), 'active' => 'contact'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function practiceAreaLinks(): array
    {
        try {
            return PracticeArea::query()->visible()->get(['name', 'slug', 'summary'])
                ->map(fn (PracticeArea $area) => [
                    'label' => $area->name,
                    'url' => route('practice-areas.show', $area),
                    'description' => $area->summary,
                ])->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>>|null */
    private static function fromMenu(string $location): ?array
    {
        try {
            if (! Schema::hasTable('menus')) {
                return null;
            }
            $menu = Menu::query()->where('location', $location)
                ->with(['items' => fn ($q) => $q->where('is_active', true)->with(['page', 'children' => fn ($c) => $c->where('is_active', true)->with('page')])])
                ->first();
        } catch (Throwable) {
            return null;
        }

        if (! $menu || $menu->items->isEmpty()) {
            return null;
        }

        $map = fn ($item) => [
            'label' => $item->label,
            'url' => $item->page ? url($item->page->slug) : ($item->url ?: '#'),
            'active' => null,
        ];

        return $menu->items->map(fn ($item) => $map($item) + ['children' => $item->children->map($map)->all()])->all();
    }
}
