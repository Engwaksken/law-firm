@php $items = collect($data['items'] ?? [])->filter(fn ($f) => filled($f['question'] ?? null) && filled($f['answer'] ?? null)); @endphp
@if ($items->isNotEmpty())
    @push('jsonld')
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org', '@type' => 'FAQPage',
            'mainEntity' => $items->map(fn ($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($f['answer'])]])->values(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endpush
    <section @if ($anchor) id="{{ $anchor }}" @endif class="section">
        <div class="container-site max-w-3xl">
            @if (filled($data['heading'] ?? null))<h2 class="text-3xl font-semibold">{{ $data['heading'] }}</h2>@endif
            <div class="mt-8 divide-y divide-black/10 border-y border-black/10">
                @foreach ($items as $faq)
                    <details class="group py-5">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-brand">
                            {{ $faq['question'] }}
                            <span aria-hidden="true" class="text-xl text-brand-secondary transition group-open:rotate-45">+</span>
                        </summary>
                        <p class="mt-3 whitespace-pre-line text-ink/80">{{ $faq['answer'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
@endif
