<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InsightController extends Controller
{
    public function index(Request $request): View
    {
        $type = array_key_exists((string) $request->string('type'), Article::TYPES) ? (string) $request->string('type') : null;

        return $this->listing($request, null, $type);
    }

    public function category(Request $request, ArticleCategory $category): View
    {
        return $this->listing($request, $category, null);
    }

    public function show(Article $article): View
    {
        abort_unless($article->isPublished(), 404);

        $article->load(['categories', 'author']);

        return view('website.insights.show', [
            'article' => $article,
            'related' => Article::query()->published()->whereKeyNot($article->id)
                ->when($article->categories->isNotEmpty(), fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereIn('article_categories.id', $article->categories->pluck('id'))))
                ->latest('published_at')->take(3)->get(),
        ]);
    }

    private function listing(Request $request, ?ArticleCategory $category, ?string $type): View
    {
        $search = trim((string) $request->string('q'));

        $articles = Article::query()->published()
            ->when($category, fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereKey($category->id)))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('excerpt', 'like', $like));
            })
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('website.insights.index', [
            'articles' => $articles,
            'categories' => ArticleCategory::query()->whereHas('articles', fn ($q) => $q->published())->orderBy('name')->get(),
            'category' => $category,
            'type' => $type,
            'search' => $search,
        ]);
    }
}
