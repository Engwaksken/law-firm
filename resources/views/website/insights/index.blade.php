@php
    $heading = $category?->name ?? ($type ? \App\Models\Article::TYPES[$type] : 'Legal insights');
    $crumbs = $category || $type ? ['Insights' => route('insights.index'), $heading => null] : ['Insights' => null];
@endphp
<x-layouts.site :title="$heading" :description="$category?->description ?? 'Articles, legal updates, commentary and publications from our advocates.'" :breadcrumbs="$crumbs">
    <x-site.page-hero eyebrow="Insights" :title="$heading" :lead="$category?->description ?? 'Articles, legal updates, commentary and publications from our advocates.'" />

    <section class="section">
        <div class="container-site grid gap-10 lg:grid-cols-[16rem_1fr]">
            <aside class="space-y-8">
                <form method="GET" action="{{ url()->current() }}" role="search">
                    <label for="q" class="form-label">Search insights</label>
                    <div class="flex gap-2">
                        <input id="q" name="q" type="search" value="{{ $search }}" class="form-input">
                        @if ($type)<input type="hidden" name="type" value="{{ $type }}">@endif
                        <button class="btn-primary px-3" type="submit"><span class="sr-only">Search</span><svg aria-hidden="true" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.2-5.2m0 0A7.5 7.5 0 105.2 5.2a7.5 7.5 0 0010.6 10.6z"/></svg></button>
                    </div>
                </form>
                <nav aria-label="Content type">
                    <h2 class="text-sm font-semibold tracking-wider text-ink/60 uppercase">Type</h2>
                    <ul class="mt-3 space-y-1 text-sm">
                        <li><a href="{{ route('insights.index') }}" @class(['block rounded px-2 py-1.5', 'bg-brand text-white' => ! $type && ! $category, 'hover:bg-white' => $type || $category])>All</a></li>
                        @foreach (\App\Models\Article::TYPES as $key => $label)
                            <li><a href="{{ route('insights.index', ['type' => $key]) }}" @class(['block rounded px-2 py-1.5', 'bg-brand text-white' => $type === $key, 'hover:bg-white' => $type !== $key]) @if ($type === $key) aria-current="page" @endif>{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </nav>
                @if ($categories->isNotEmpty())
                    <nav aria-label="Categories">
                        <h2 class="text-sm font-semibold tracking-wider text-ink/60 uppercase">Categories</h2>
                        <ul class="mt-3 space-y-1 text-sm">
                            @foreach ($categories as $cat)
                                <li><a href="{{ route('insights.category', $cat) }}" @class(['block rounded px-2 py-1.5', 'bg-brand text-white' => $category?->is($cat), 'hover:bg-white' => ! $category?->is($cat)])>{{ $cat->name }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
            </aside>

            <div>
                @if ($articles->isEmpty())
                    <p class="text-ink/70">{{ $search ? 'No insights match your search.' : 'No insights have been published here yet.' }}</p>
                @else
                    <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($articles as $article)
                            <x-site.article-card :article="$article" />
                        @endforeach
                    </div>
                    <div class="mt-10">{{ $articles->links() }}</div>
                @endif
            </div>
        </div>
    </section>
</x-layouts.site>
