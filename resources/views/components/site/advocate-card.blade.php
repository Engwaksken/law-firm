@props(['advocate'])
<article class="card group relative overflow-hidden">
    <div class="aspect-[4/5] bg-brand/5">
        @if ($advocate->photo_path)
            <img src="{{ Storage::disk('public')->url($advocate->photo_path) }}" alt="Portrait of {{ $advocate->name }}"
                 loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
        @else
            <div aria-hidden="true" class="grid h-full w-full place-items-center bg-brand font-display text-5xl font-semibold text-brand-accent">
                {{ str($advocate->name)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
            </div>
        @endif
    </div>
    <div class="p-5">
        <h3 class="text-lg font-semibold">
            <a href="{{ route('advocates.show', $advocate) }}" class="after:absolute after:inset-0">{{ $advocate->name }}</a>
        </h3>
        @if ($advocate->title)<p class="mt-1 text-sm text-brand-secondary">{{ $advocate->title }}</p>@endif
        @if ($advocate->relationLoaded('practiceAreas') && $advocate->practiceAreas->isNotEmpty())
            <p class="mt-2 line-clamp-2 text-xs text-ink/60">{{ $advocate->practiceAreas->pluck('name')->implode(' · ') }}</p>
        @endif
    </div>
</article>
