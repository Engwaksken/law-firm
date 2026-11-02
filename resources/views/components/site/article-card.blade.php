@props(['article', 'featured' => false])
<article class="card group relative flex h-full flex-col overflow-hidden">
    @if ($article->featured_image)
        <div class="{{ $featured ? 'aspect-[16/9]' : 'aspect-[3/2]' }} overflow-hidden bg-brand/5">
            <img src="{{ Storage::disk('public')->url($article->featured_image) }}" alt="" loading="lazy"
                 class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
        </div>
    @endif
    <div class="flex flex-1 flex-col p-6">
        <p class="eyebrow">{{ $article->typeLabel() }}</p>
        <h3 class="mt-2 {{ $featured ? 'text-2xl' : 'text-lg' }} font-semibold leading-snug">
            <a href="{{ route('insights.show', $article) }}" class="after:absolute after:inset-0">{{ $article->title }}</a>
        </h3>
        @if ($article->excerpt)
            <p class="mt-3 line-clamp-3 flex-1 text-sm text-ink/70">{{ $article->excerpt }}</p>
        @endif
        <p class="mt-5 text-xs text-ink/50">
            <time datetime="{{ $article->published_at?->toDateString() }}">{{ $article->published_at?->format('j F Y') }}</time>
            · {{ $article->readingMinutes() }} min read
        </p>
    </div>
</article>
