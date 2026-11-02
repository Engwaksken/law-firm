@php
    $style = $data['style'] ?? 'secondary';
    $button = \App\Support\SafeUrl::make($data['button_url'] ?? null);
    $light = $style === 'light';
@endphp
<section @if ($anchor) id="{{ $anchor }}" @endif @class(['bg-brand-secondary' => $style === 'secondary', 'bg-brand' => $style === 'primary', 'bg-white border-y border-black/5' => $light])>
    <div class="container-site flex flex-col items-start gap-6 py-14 md:flex-row md:items-center md:justify-between">
        <div class="max-w-2xl">
            <h2 @class(['text-3xl font-semibold', 'text-white' => ! $light])>{{ $data['title'] ?? '' }}</h2>
            @if (filled($data['text'] ?? null))<p @class(['mt-3', 'text-white/80' => ! $light, 'text-ink/70' => $light])>{{ $data['text'] }}</p>@endif
        </div>
        @if ($button && filled($data['button_label'] ?? null))
            <a href="{{ $button }}" class="{{ $light ? 'btn-primary' : 'btn-accent' }}">{{ $data['button_label'] }}</a>
        @endif
    </div>
</section>
