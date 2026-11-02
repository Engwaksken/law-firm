@php
    use App\Support\Settings;
    $heroImage = Settings::get('home_hero_image');
    $stats = collect(Settings::get('home_stats'))->filter(fn ($s) => is_numeric($s['value'] ?? null) && filled($s['label'] ?? null));
    $why = collect(Settings::get('home_why_points'))->filter(fn ($p) => filled($p['title'] ?? null));
@endphp
<x-layouts.site>
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-brand text-white">
        @if ($heroImage)
            <img src="{{ Storage::disk('public')->url($heroImage) }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-25">
        @endif
        <div aria-hidden="true" class="absolute inset-0" style="background: linear-gradient(110deg, var(--color-brand) 35%, transparent), radial-gradient(circle at 90% 10%, var(--color-brand-secondary), transparent 50%);"></div>
        <div class="container-site relative py-24 sm:py-32 lg:py-40">
            <p class="text-xs font-semibold tracking-[0.25em] text-brand-accent uppercase">{{ Settings::get('firm_name') }}</p>
            <h1 class="mt-5 max-w-4xl text-4xl leading-tight font-semibold text-white sm:text-5xl lg:text-6xl">{{ Settings::get('home_hero_title') }}</h1>
            <p class="mt-6 max-w-2xl text-lg text-white/80 sm:text-xl">{{ Settings::get('home_hero_subtitle') }}</p>
            <div class="mt-10 flex flex-wrap gap-4">
                <a href="{{ route('consultation') }}" class="btn-accent">Book a consultation</a>
                <a href="{{ route('practice-areas.index') }}" class="btn-ghost-light">Explore our practice areas</a>
            </div>
        </div>
    </section>

    {{-- Introduction --}}
    <section class="section">
        <div class="container-site grid items-center gap-12 lg:grid-cols-2">
            <div>
                <p class="eyebrow">Who we are</p>
                <h2 class="mt-3 text-3xl font-semibold sm:text-4xl">{{ Settings::get('home_intro_title') }}</h2>
            </div>
            <div>
                <x-site.rich :html="Settings::get('home_intro_body')" class="text-lg" />
                <a href="{{ route('about') }}" class="btn-outline mt-2">More about the firm</a>
            </div>
        </div>
    </section>

    {{-- Impact statistics: only shown when the firm has entered figures. --}}
    @if ($stats->isNotEmpty())
        <section class="border-y border-black/5 bg-white" aria-labelledby="stats-heading">
            <h2 id="stats-heading" class="sr-only">The firm in numbers</h2>
            <dl @class(['container-site grid grid-cols-2 gap-8 py-14', 'md:grid-cols-3' => $stats->count() === 3, 'md:grid-cols-4' => $stats->count() >= 4])>
                @foreach ($stats as $stat)
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

    {{-- Practice areas --}}
    @if ($practiceAreas->isNotEmpty())
        <section class="section bg-white" aria-labelledby="areas-heading">
            <div class="container-site">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="eyebrow">What we do</p>
                        <h2 id="areas-heading" class="mt-3 text-3xl font-semibold sm:text-4xl">Practice areas</h2>
                    </div>
                    <a href="{{ route('practice-areas.index') }}" class="text-sm font-semibold text-brand-secondary hover:underline">All practice areas →</a>
                </div>
                <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($practiceAreas as $area)
                        <x-site.practice-card :area="$area" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Advocates --}}
    @if ($advocates->isNotEmpty())
        <section class="section" aria-labelledby="advocates-heading">
            <div class="container-site">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="eyebrow">Our people</p>
                        <h2 id="advocates-heading" class="mt-3 text-3xl font-semibold sm:text-4xl">Our advocates</h2>
                    </div>
                    <a href="{{ route('advocates.index') }}" class="text-sm font-semibold text-brand-secondary hover:underline">Meet the team →</a>
                </div>
                <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($advocates as $advocate)
                        <x-site.advocate-card :advocate="$advocate" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Why choose us --}}
    @if ($why->isNotEmpty())
        <section class="section bg-brand text-white" aria-labelledby="why-heading">
            <div class="container-site">
                <h2 id="why-heading" class="max-w-2xl text-3xl font-semibold text-white sm:text-4xl">{{ Settings::get('home_why_title') }}</h2>
                <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($why as $point)
                        <div class="border-t-2 border-brand-accent pt-6">
                            <h3 class="text-lg font-semibold text-white">{{ $point['title'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-white/75">{{ $point['text'] ?? '' }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Legal insights --}}
    @if ($insights->isNotEmpty())
        <section class="section" aria-labelledby="insights-heading">
            <div class="container-site">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="eyebrow">Thinking</p>
                        <h2 id="insights-heading" class="mt-3 text-3xl font-semibold sm:text-4xl">Legal insights</h2>
                    </div>
                    <a href="{{ route('insights.index') }}" class="text-sm font-semibold text-brand-secondary hover:underline">All insights →</a>
                </div>
                <div class="mt-10 grid gap-6 lg:grid-cols-3">
                    @foreach ($insights as $article)
                        <x-site.article-card :article="$article" :featured="$loop->first" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- News --}}
    @if ($news->isNotEmpty())
        <section class="section bg-white pt-0 sm:pt-0" aria-labelledby="news-heading">
            <div class="container-site border-t border-black/5 pt-16">
                <h2 id="news-heading" class="text-2xl font-semibold">News &amp; announcements</h2>
                <ul class="mt-6 divide-y divide-black/5">
                    @foreach ($news as $item)
                        <li class="flex flex-col gap-1 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                            <time class="w-32 shrink-0 text-sm text-ink/50" datetime="{{ $item->published_at->toDateString() }}">{{ $item->published_at->format('j M Y') }}</time>
                            <a href="{{ route('insights.show', $item) }}" class="font-medium text-brand hover:text-brand-secondary">{{ $item->title }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- Testimonials: only consented and approved ones (spec §5.1). --}}
    @if ($testimonials->isNotEmpty())
        <section class="section" aria-labelledby="testimonials-heading">
            <div class="container-site">
                <h2 id="testimonials-heading" class="text-center text-3xl font-semibold">What our clients say</h2>
                <div @class(['mt-10 grid gap-6', 'lg:grid-cols-2' => $testimonials->count() === 2, 'lg:grid-cols-3' => $testimonials->count() >= 3])>
                    @foreach ($testimonials as $testimonial)
                        <figure class="card p-8">
                            <blockquote class="text-lg leading-relaxed text-ink/80">“{{ $testimonial->quote }}”</blockquote>
                            <figcaption class="mt-6 text-sm">
                                <span class="font-semibold text-brand">{{ $testimonial->author_name }}</span>
                                @if ($testimonial->author_title)<span class="text-ink/60">, {{ $testimonial->author_title }}</span>@endif
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Contact + newsletter --}}
    <section class="section bg-white" aria-labelledby="contact-heading">
        <div class="container-site grid gap-12 lg:grid-cols-2">
            <div>
                <p class="eyebrow">Get in touch</p>
                <h2 id="contact-heading" class="mt-3 text-3xl font-semibold">Contact the firm</h2>
                <dl class="mt-8 space-y-5 text-sm">
                    @if ($address = Settings::get('contact_address'))
                        <div><dt class="font-semibold text-brand">Address</dt><dd class="mt-1 whitespace-pre-line text-ink/70">{{ $address }}</dd></div>
                    @endif
                    @if ($phone = Settings::get('contact_phone'))
                        <div><dt class="font-semibold text-brand">Phone</dt><dd class="mt-1"><a class="text-ink/70 hover:text-brand" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a></dd></div>
                    @endif
                    @if ($email = Settings::get('contact_email'))
                        <div><dt class="font-semibold text-brand">Email</dt><dd class="mt-1"><a class="text-ink/70 hover:text-brand" href="mailto:{{ $email }}">{{ $email }}</a></dd></div>
                    @endif
                    <div><dt class="font-semibold text-brand">Office hours</dt><dd class="mt-1 text-ink/70">{{ Settings::get('office_hours') }}</dd></div>
                </dl>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('contact') }}" class="btn-primary">Send us a message</a>
                    <a href="{{ route('consultation') }}" class="btn-outline">Book a consultation</a>
                </div>
            </div>
            <div class="rounded-lg bg-canvas p-8">
                <h3 class="text-xl font-semibold">Legal updates in your inbox</h3>
                <p class="mt-2 text-sm text-ink/70">Occasional newsletters and updates on legal developments. Unsubscribe at any time.</p>
                <div class="mt-6"><livewire:newsletter-signup /></div>
            </div>
        </div>
    </section>

    <x-site.cta />
</x-layouts.site>
