<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CmsPageController extends Controller
{
    public function show(string $slug): Response|RedirectResponse
    {
        abort_if(Page::isReservedSlug($slug), 404);

        $page = Page::query()->published()->where('slug', $slug)->firstOrFail();

        // The page chosen as homepage lives at "/" only (one canonical URL).
        if ((int) Settings::get('homepage_page_id') === $page->id) {
            return redirect()->route('home', status: 301);
        }

        return $this->render($page, $page->liveContent());
    }

    /** Working copy, for staff, including drafts and unpublished edits. */
    public function preview(Page $page): Response
    {
        Gate::authorize('view', $page);

        return $this->render($page, $page->snapshot(), preview: true)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** @param array{page: array, blocks: list<array>} $content */
    public static function render(Page $page, array $content, bool $preview = false, bool $isHome = false): Response
    {
        return response()->view('cms.page', [
            'page' => $page,
            'meta' => $content['page'],
            'blocks' => collect($content['blocks'])->filter(fn ($b) => ($b['is_active'] ?? true) && ($b['data']['is_active'] ?? true))->values(),
            'preview' => $preview,
            'isHome' => $isHome,
        ]);
    }
}
