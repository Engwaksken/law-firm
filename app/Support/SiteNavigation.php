<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\PracticeArea;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Builds public navigation. A CMS menu (Website → Menus) at location "header"
 * or "footer" wins when it has visible items; otherwise a sensible default is
 * used, with a practice-area mega menu in the header.
 *
 * Each item: ['label', 'url' (string|null: null = non-link group), 'active' (route pattern|null),
 *             'external' (bool), 'children' => list]
 */
class SiteNavigation
{
    /** @return list<array<string, mixed>> */
    public static function header(): array
    {
        return static::fromMenu('header') ?? array_values(array_filter([
            ['label' => 'About', 'url' => route('about'), 'active' => 'about'],
            ['label' => 'Practice areas', 'url' => route('practice-areas.index'), 'active' => 'practice-areas.*', 'mega' => true,
                'children' => static::practiceAreaLinks()],
            ['label' => 'Our advocates', 'url' => route('advocates.index'), 'active' => 'advocates.*'],
            ['label' => 'Insights', 'url' => route('insights.index'), 'active' => 'insights.*'],
            static::hasUpcomingEvents() ? ['label' => 'Events', 'url' => route('events.index'), 'active' => 'events.*'] : null,
            ['label' => 'Careers', 'url' => route('careers.index'), 'active' => 'careers.*'],
            ['label' => 'Contact', 'url' => route('contact'), 'active' => 'contact'],
        ]));
    }

    /**
     * Footer "The firm" links from the CMS footer menu (flattened), or null to use the built-in list.
     *
     * @return list<array<string, mixed>>|null
     */
    public static function footer(): ?array
    {
        $items = static::fromMenu('footer');
        if ($items === null) {
            return null;
        }

        $flat = [];
        foreach ($items as $item) {
            if ($item['url'] !== null) {
                $flat[] = ['label' => $item['label'], 'url' => $item['url'], 'external' => $item['external']];
            }
            foreach ($item['children'] as $child) {
                $flat[] = ['label' => $child['label'], 'url' => $child['url'], 'external' => $child['external']];
            }
        }

        return $flat ?: null;
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

    public static function hasUpcomingEvents(): bool
    {
        return (bool) rescue(fn () => Event::query()->upcoming()->exists(), false, false);
    }

    /** @return list<array<string, mixed>>|null */
    private static function fromMenu(string $location): ?array
    {
        try {
            if (! Schema::hasTable('menus')) {
                return null;
            }
            $menu = Menu::query()->where('location', $location)
                ->with(['items' => fn ($query) => $query->where('is_active', true)
                    ->with(['page', 'children' => fn ($query) => $query->where('is_active', true)->with('page')])])
                ->first();
        } catch (Throwable) {
            return null;
        }

        if (! $menu) {
            return null;
        }

        $map = fn (MenuItem $item) => [
            'label' => $item->label,
            'url' => $item->href(),
            'active' => null,
            'external' => $item->open_in_new_tab,
        ];

        $items = [];
        foreach ($menu->items as $item) {
            // Children whose target is unavailable (draft page, unsafe URL) are dropped.
            $children = $item->children->map($map)->filter(fn (array $child) => $child['url'] !== null)->values()->all();
            $entry = $map($item) + ['children' => $children];

            // A parent without a usable link is kept only as a grouping for its children.
            if ($entry['url'] === null && $children === []) {
                continue;
            }
            $items[] = $entry;
        }

        return $items ?: null;
    }
}
