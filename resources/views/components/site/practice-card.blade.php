@props(['area'])
<article class="card group relative flex h-full flex-col p-6">
    <span aria-hidden="true" class="mb-5 block h-1 w-10 rounded bg-brand-accent transition-all group-hover:w-16"></span>
    <h3 class="text-xl font-semibold">
        <a href="{{ route('practice-areas.show', $area) }}" class="after:absolute after:inset-0">{{ $area->name }}</a>
    </h3>
    <p class="mt-3 flex-1 text-sm leading-relaxed text-ink/70">{{ $area->summary ?? str($area->description)->limit(140) }}</p>
    <span aria-hidden="true" class="mt-5 text-sm font-semibold text-brand-secondary">Learn more →</span>
</article>
