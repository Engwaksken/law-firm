@php $items = collect($data['items'] ?? [])->filter(fn ($s) => is_numeric($s['value'] ?? null) && filled($s['label'] ?? null)); @endphp
@if ($items->isNotEmpty())
    <section @if ($anchor) id="{{ $anchor }}" @endif class="border-y border-black/5 bg-white" @if (filled($data['heading'] ?? null)) aria-label="{{ $data['heading'] }}" @endif>
        @if (filled($data['heading'] ?? null))<h2 class="container-site pt-12 text-center text-2xl font-semibold">{{ $data['heading'] }}</h2>@endif
        <dl @class(['container-site grid grid-cols-2 gap-8 py-12', 'md:grid-cols-3' => $items->count() === 3, 'md:grid-cols-4' => $items->count() >= 4])>
            @foreach ($items as $stat)
                <div class="flex flex-col-reverse text-center">
                    <dt class="mt-2 text-sm text-ink/60">{{ $stat['label'] }}</dt>
                    <dd class="font-display text-4xl font-semibold text-brand sm:text-5xl">
                        <span x-data="countUp({{ (int) $stat['value'] }})" x-text="formatted">{{ number_format((int) $stat['value']) }}</span>{{ $stat['suffix'] ?? '' }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>
@endif
