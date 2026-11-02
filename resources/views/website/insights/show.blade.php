<x-layouts.site :title="$article->meta_title ?: $article->title" :description="$article->meta_description ?: $article->excerpt"
    :image="$article->featured_image ? Storage::disk('public')->url($article->featured_image) : null"
    :breadcrumbs="['Insights' => route('insights.index'), $article->title => null]">

    <article>
        <header class="bg-brand text-white">
            <div class="container-site max-w-4xl py-16">
                <p class="text-xs font-semibold tracking-[0.2em] text-brand-accent uppercase">{{ $article->typeLabel() }}</p>
                <h1 class="mt-4 text-3xl leading-tight font-semibold text-white sm:text-5xl">{{ $article->title }}</h1>
                <p class="mt-6 text-sm text-white/70">
                    @if ($article->author)By {{ $article->author->name }} · @endif
                    <time datetime="{{ $article->published_at->toDateString() }}">{{ $article->published_at->format('j F Y') }}</time>
                    · {{ $article->readingMinutes() }} min read
                </p>
            </div>
        </header>

        <div class="container-site max-w-4xl py-14">
            @if ($article->featured_image)
                <img src="{{ Storage::disk('public')->url($article->featured_image) }}" alt="" class="mb-10 w-full rounded-lg">
            @endif
            @if ($article->excerpt)
                <p class="mb-8 text-xl leading-relaxed text-ink/80">{{ $article->excerpt }}</p>
            @endif
            <x-site.rich :html="$article->body" class="text-lg" />

            @if ($article->attachment_path)
                <div class="mt-10 flex items-center justify-between gap-4 rounded-lg border border-black/10 bg-white p-5">
                    <div>
                        <p class="font-semibold text-brand">Download the full document</p>
                        <p class="text-xs text-ink/60">{{ strtoupper(pathinfo($article->attachment_path, PATHINFO_EXTENSION)) }} file</p>
                    </div>
                    <a href="{{ Storage::disk('public')->url($article->attachment_path) }}" class="btn-primary" download>Download</a>
                </div>
            @endif

            <footer class="mt-12 flex flex-wrap gap-2 border-t border-black/10 pt-6">
                @foreach ($article->categories as $category)
                    <a href="{{ route('insights.category', $category) }}" class="rounded-full bg-brand/5 px-3 py-1 text-xs font-medium text-brand hover:bg-brand hover:text-white">{{ $category->name }}</a>
                @endforeach
                @foreach ($article->tags ?? [] as $tag)
                    <span class="rounded-full border border-black/10 px-3 py-1 text-xs text-ink/60">#{{ $tag }}</span>
                @endforeach
            </footer>

            <p class="mt-8 rounded bg-brand-accent/10 p-4 text-sm text-ink/70">
                This article provides general information only and is not legal advice. For advice on your specific circumstances, please
                <a href="{{ route('consultation') }}" class="font-semibold text-brand-secondary underline">book a consultation</a>.
            </p>
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="section bg-white">
            <div class="container-site">
                <h2 class="text-2xl font-semibold">Related insights</h2>
                <div class="mt-8 grid gap-6 lg:grid-cols-3">
                    @foreach ($related as $item)
                        <x-site.article-card :article="$item" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts.site>
