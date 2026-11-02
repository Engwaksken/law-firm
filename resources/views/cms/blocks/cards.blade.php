@php $cols = (int) ($data['columns'] ?? 3); @endphp
<section @if ($anchor) id="{{ $anchor }}" @endif class="section">
    <div class="container-site">
        @if (filled($data['heading'] ?? null))<h2 class="text-3xl font-semibold">{{ $data['heading'] }}</h2>@endif
        @if (filled($data['intro'] ?? null))<p class="mt-3 max-w-2xl text-ink/70">{{ $data['intro'] }}</p>@endif
        <div @class(['mt-10 grid gap-6 sm:grid-cols-2', 'lg:grid-cols-3' => $cols === 3, 'lg:grid-cols-4' => $cols === 4])>
            @foreach ($data['items'] ?? [] as $item)
                @php $url = \App\Support\SafeUrl::make($item['url'] ?? null); @endphp
                <article class="card relative flex h-full flex-col p-6">
                    <span aria-hidden="true" class="mb-5 block h-1 w-10 rounded bg-brand-accent"></span>
                    <h3 class="text-xl font-semibold">
                        @if ($url)<a href="{{ $url }}" class="after:absolute after:inset-0">{{ $item['title'] }}</a>@else{{ $item['title'] }}@endif
                    </h3>
                    @if (filled($item['text'] ?? null))<p class="mt-3 flex-1 text-sm leading-relaxed text-ink/70">{{ $item['text'] }}</p>@endif
                </article>
            @endforeach
        </div>
    </div>
</section>
