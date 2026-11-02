@php
    $image = ($data['image_id'] ?? null) ? \App\Models\Media::find($data['image_id']) : null;
    $dark = ($data['style'] ?? 'dark') === 'dark';
    $primary = \App\Support\SafeUrl::make($data['primary_url'] ?? null);
    $secondary = \App\Support\SafeUrl::make($data['secondary_url'] ?? null);
@endphp
<section @if ($anchor) id="{{ $anchor }}" @endif @class(['relative overflow-hidden', 'bg-brand text-white' => $dark, 'bg-white' => ! $dark])>
    @if ($image)
        <img src="{{ $image->url() }}" alt="" @class(['absolute inset-0 h-full w-full object-cover', 'opacity-25' => $dark, 'opacity-10' => ! $dark])>
    @endif
    @if ($dark)
        <div aria-hidden="true" class="absolute inset-0" style="background: linear-gradient(110deg, var(--color-brand) 35%, transparent), radial-gradient(circle at 90% 10%, var(--color-brand-secondary), transparent 50%);"></div>
    @endif
    <div class="container-site relative py-20 sm:py-28">
        @if (filled($data['eyebrow'] ?? null))
            <p @class(['text-xs font-semibold tracking-[0.25em] uppercase', 'text-brand-accent' => $dark, 'text-brand-secondary' => ! $dark])>{{ $data['eyebrow'] }}</p>
        @endif
        <h1 @class(['mt-4 max-w-4xl text-4xl leading-tight font-semibold sm:text-5xl', 'text-white' => $dark])>{{ $data['title'] }}</h1>
        @if (filled($data['subtitle'] ?? null))
            <p @class(['mt-6 max-w-2xl text-lg sm:text-xl', 'text-white/80' => $dark, 'text-ink/70' => ! $dark])>{{ $data['subtitle'] }}</p>
        @endif
        @if (($primary && filled($data['primary_label'] ?? null)) || ($secondary && filled($data['secondary_label'] ?? null)))
            <div class="mt-10 flex flex-wrap gap-4">
                @if ($primary && filled($data['primary_label'] ?? null))
                    <a href="{{ $primary }}" class="{{ $dark ? 'btn-accent' : 'btn-primary' }}">{{ $data['primary_label'] }}</a>
                @endif
                @if ($secondary && filled($data['secondary_label'] ?? null))
                    <a href="{{ $secondary }}" class="{{ $dark ? 'btn-ghost-light' : 'btn-outline' }}">{{ $data['secondary_label'] }}</a>
                @endif
            </div>
        @endif
    </div>
</section>
