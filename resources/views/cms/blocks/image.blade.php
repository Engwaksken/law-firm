@php
    $image = ($data['image_id'] ?? null) ? \App\Models\Media::find($data['image_id']) : null;
    $size = $data['size'] ?? 'wide';
@endphp
@if ($image)
    <figure @if ($anchor) id="{{ $anchor }}" @endif @class(['my-12', 'container-site max-w-3xl' => $size === 'narrow', 'container-site' => $size === 'wide'])>
        <img src="{{ $image->url() }}" alt="{{ $image->alt_text }}" loading="lazy" @class(['w-full object-cover', 'rounded-lg' => $size !== 'full'])
             @if ($image->width) width="{{ $image->width }}" height="{{ $image->height }}" @endif>
        @if (filled($data['caption'] ?? $image->caption))
            <figcaption @class(['mt-3 text-sm text-ink/60', 'container-site' => $size === 'full'])>{{ $data['caption'] ?? $image->caption }}</figcaption>
        @endif
    </figure>
@endif
