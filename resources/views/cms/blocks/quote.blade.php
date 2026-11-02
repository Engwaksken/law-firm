<section @if ($anchor) id="{{ $anchor }}" @endif class="section">
    <figure class="container-site max-w-3xl text-center">
        <span aria-hidden="true" class="font-display text-6xl leading-none text-brand-accent">“</span>
        <blockquote class="font-display text-2xl leading-snug text-brand sm:text-3xl">{{ $data['quote'] ?? '' }}</blockquote>
        @if (filled($data['attribution'] ?? null))
            <figcaption class="mt-6 text-sm">
                <span class="font-semibold text-brand">{{ $data['attribution'] }}</span>
                @if (filled($data['role'] ?? null))<span class="text-ink/60">, {{ $data['role'] }}</span>@endif
            </figcaption>
        @endif
    </figure>
</section>
