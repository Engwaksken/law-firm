@php $faqs = collect($area->faqs)->filter(fn ($f) => filled($f['question'] ?? null) && filled($f['answer'] ?? null)); @endphp
<x-layouts.site :title="$area->meta_title ?: $area->name" :description="$area->meta_description ?: ($area->summary ?? $area->description)"
    :image="$area->image_path ? Storage::disk('public')->url($area->image_path) : null"
    :breadcrumbs="['Practice areas' => route('practice-areas.index'), $area->name => null]">

    @if ($faqs->isNotEmpty())
        <x-slot:head>
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faqs->map(fn ($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($f['answer'])]])->values(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        </x-slot:head>
    @endif

    <x-site.page-hero eyebrow="Practice area" :title="$area->name" :lead="$area->summary" />

    <section class="section">
        <div class="container-site grid gap-12 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @if ($area->image_path)
                    <img src="{{ Storage::disk('public')->url($area->image_path) }}" alt="" class="mb-10 aspect-[16/7] w-full rounded-lg object-cover">
                @endif
                <x-site.rich :html="$area->body ?: $area->description" class="text-lg" />

                @if ($faqs->isNotEmpty())
                    <h2 class="mt-14 text-2xl font-semibold">Frequently asked questions</h2>
                    <div class="mt-6 divide-y divide-black/10 border-y border-black/10">
                        @foreach ($faqs as $faq)
                            <details class="group py-5">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-brand">
                                    {{ $faq['question'] }}
                                    <span aria-hidden="true" class="text-xl text-brand-secondary transition group-open:rotate-45">+</span>
                                </summary>
                                <x-site.rich :html="$faq['answer']" class="mt-3 text-ink/80" />
                            </details>
                        @endforeach
                    </div>
                @endif
            </div>

            <aside class="space-y-8">
                <div class="rounded-lg bg-brand p-6 text-white">
                    <h2 class="text-xl font-semibold text-white">Need advice on {{ strtolower($area->name) }}?</h2>
                    <p class="mt-2 text-sm text-white/75">Request a consultation and we will match you with an advocate experienced in this area.</p>
                    <a href="{{ route('consultation', ['practice_area' => $area->slug]) }}" class="btn-accent mt-5 w-full">Book a consultation</a>
                </div>

                @if ($advocates->isNotEmpty())
                    <div>
                        <h2 class="text-lg font-semibold">Advocates in this area</h2>
                        <ul class="mt-4 space-y-3">
                            @foreach ($advocates as $advocate)
                                <li>
                                    <a href="{{ route('advocates.show', $advocate) }}" class="flex items-center gap-3 rounded-lg p-2 hover:bg-white">
                                        @if ($advocate->photo_path)
                                            <img src="{{ Storage::disk('public')->url($advocate->photo_path) }}" alt="" class="h-12 w-12 rounded-full object-cover">
                                        @else
                                            <span aria-hidden="true" class="grid h-12 w-12 place-items-center rounded-full bg-brand text-sm font-semibold text-brand-accent">{{ mb_substr($advocate->name, 0, 1) }}</span>
                                        @endif
                                        <span>
                                            <span class="block font-semibold text-brand">{{ $advocate->name }}</span>
                                            <span class="block text-xs text-ink/60">{{ $advocate->title }}</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($related->isNotEmpty())
                    <nav aria-label="Other practice areas">
                        <h2 class="text-lg font-semibold">Other practice areas</h2>
                        <ul class="mt-4 space-y-2 text-sm">
                            @foreach ($related as $other)
                                <li><a href="{{ route('practice-areas.show', $other) }}" class="text-brand-secondary hover:underline">{{ $other->name }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
            </aside>
        </div>
    </section>

    @if ($insights->isNotEmpty())
        <section class="section bg-white">
            <div class="container-site">
                <h2 class="text-2xl font-semibold">Related insights</h2>
                <div class="mt-8 grid gap-6 lg:grid-cols-3">
                    @foreach ($insights as $article)
                        <x-site.article-card :article="$article" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts.site>
