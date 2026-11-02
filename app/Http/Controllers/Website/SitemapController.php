<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Advocate;
use App\Models\Article;
use App\Models\Career;
use App\Models\PracticeArea;
use Illuminate\Http\Response;

/** Lists only public content: published, verified and open records. */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([
            ['loc' => route('home')], ['loc' => route('about')], ['loc' => route('practice-areas.index')],
            ['loc' => route('advocates.index')], ['loc' => route('insights.index')], ['loc' => route('careers.index')],
            ['loc' => route('contact')], ['loc' => route('consultation')], ['loc' => route('privacy')],
        ])
            ->merge(PracticeArea::query()->visible()->get()->map(fn ($a) => ['loc' => route('practice-areas.show', $a), 'lastmod' => $a->updated_at]))
            ->merge(Advocate::query()->published()->get()->map(fn ($a) => ['loc' => route('advocates.show', $a), 'lastmod' => $a->updated_at]))
            ->merge(Article::query()->published()->get()->map(fn ($a) => ['loc' => route('insights.show', $a), 'lastmod' => $a->updated_at]))
            ->merge(Career::query()->open()->get()->map(fn ($c) => ['loc' => route('careers.show', $c), 'lastmod' => $c->updated_at]));

        return response()->view('website.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }
}
