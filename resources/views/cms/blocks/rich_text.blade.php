<section @if ($anchor) id="{{ $anchor }}" @endif class="section">
    <div @class(['container-site', 'max-w-3xl' => ($data['width'] ?? 'narrow') === 'narrow'])>
        @if (filled($data['heading'] ?? null))<h2 class="mb-6 text-3xl font-semibold">{{ $data['heading'] }}</h2>@endif
        <x-site.rich :html="$data['body'] ?? null" class="text-lg" />
    </div>
</section>
