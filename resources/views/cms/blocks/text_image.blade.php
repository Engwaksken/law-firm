@php
    $image = ($data['image_id'] ?? null) ? \App\Models\Media::find($data['image_id']) : null;
    $button = \App\Support\SafeUrl::make($data['button_url'] ?? null);
    $left = ($data['image_position'] ?? 'right') === 'left';
@endphp
<section @if ($anchor) id="{{ $anchor }}" @endif class="section">
    <div class="container-site grid items-center gap-12 lg:grid-cols-2">
        <div @class(['lg:order-2' => $left])>
            @if (filled($data['heading'] ?? null))<h2 class="mb-6 text-3xl font-semibold">{{ $data['heading'] }}</h2>@endif
            <x-site.rich :html="$data['body'] ?? null" />
            @if ($button && filled($data['button_label'] ?? null))
                <a href="{{ $button }}" class="btn-primary mt-2">{{ $data['button_label'] }}</a>
            @endif
        </div>
        @if ($image)
            <img src="{{ $image->url() }}" alt="{{ $image->alt_text }}" loading="lazy" @class(['w-full rounded-lg object-cover shadow-sm', 'lg:order-1' => $left])>
        @endif
    </div>
</section>
