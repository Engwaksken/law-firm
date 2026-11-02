@php
    $articles = \App\Models\Article::query()->published()
        ->when(filled($data['type'] ?? null), fn ($query) => $query->where('type', $data['type']))
        ->latest('published_at')->take(max(1, min(12, (int) ($data['limit'] ?? 3))))->get();
@endphp
@if ($articles->isNotEmpty())
    <section @if ($anchor) id="{{ $anchor }}" @endif class="section">
        <div class="container-site">
            <div class="flex flex-wrap items-end justify-between gap-4">
                @if (filled($data['heading'] ?? null))<h2 class="text-3xl font-semibold sm:text-4xl">{{ $data['heading'] }}</h2>@endif
                <a href="{{ route('insights.index', filled($data['type'] ?? null) ? ['type' => $data['type']] : []) }}" class="text-sm font-semibold text-brand-secondary hover:underline">All insights →</a>
            </div>
            <div class="mt-10 grid gap-6 lg:grid-cols-3">
                @foreach ($articles as $article)<x-site.article-card :article="$article" />@endforeach
            </div>
        </div>
    </section>
@endif
