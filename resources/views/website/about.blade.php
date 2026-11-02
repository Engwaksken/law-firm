@php
    use App\Support\Settings;
    $values = collect(Settings::get('about_values'))->filter(fn ($v) => filled($v['title'] ?? null));
    $affiliations = collect(Settings::get('about_affiliations'))->filter(fn ($a) => filled($a['name'] ?? null));
    $milestones = collect(Settings::get('about_milestones'))->filter(fn ($m) => filled($m['text'] ?? null))->sortBy('year');
    $safeUrl = fn ($url) => \Illuminate\Support\Str::sanitizeUrl($url);
@endphp
<x-layouts.site title="About the firm" :description="Settings::get('home_intro_body')" :breadcrumbs="['About' => null]">
    <x-site.page-hero eyebrow="About the firm" :title="Settings::get('firm_name')" :lead="Settings::get('firm_tagline')" />

    <section class="section">
        <div class="container-site grid gap-12 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <h2 class="text-3xl font-semibold">{{ Settings::get('home_intro_title') }}</h2>
                <x-site.rich :html="Settings::get('home_intro_body')" class="mt-6 text-lg" />
                @if (Settings::get('about_history'))
                    <h2 class="mt-12 text-2xl font-semibold">Our history</h2>
                    <x-site.rich :html="Settings::get('about_history')" class="mt-4" />
                @endif
            </div>
            @if (Settings::get('about_mission') || Settings::get('about_vision'))
                <aside class="space-y-6">
                    @if (Settings::get('about_mission'))
                        <div class="card p-6">
                            <h2 class="text-lg font-semibold">Our mission</h2>
                            <x-site.rich :html="Settings::get('about_mission')" class="mt-3 text-sm" />
                        </div>
                    @endif
                    @if (Settings::get('about_vision'))
                        <div class="card p-6">
                            <h2 class="text-lg font-semibold">Our vision</h2>
                            <x-site.rich :html="Settings::get('about_vision')" class="mt-3 text-sm" />
                        </div>
                    @endif
                </aside>
            @endif
        </div>
    </section>

    @if ($values->isNotEmpty())
        <section class="section bg-brand text-white" aria-labelledby="values-heading">
            <div class="container-site">
                <h2 id="values-heading" class="text-3xl font-semibold text-white">Our core values</h2>
                <div class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($values as $value)
                        <div class="border-t-2 border-brand-accent pt-5">
                            <h3 class="text-lg font-semibold text-white">{{ $value['title'] }}</h3>
                            <p class="mt-2 text-sm text-white/75">{{ $value['text'] ?? '' }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($leaders->isNotEmpty())
        <section class="section" aria-labelledby="leadership-heading">
            <div class="container-site">
                <h2 id="leadership-heading" class="text-3xl font-semibold">Leadership</h2>
                <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($leaders as $advocate)
                        <x-site.advocate-card :advocate="$advocate" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($milestones->isNotEmpty())
        <section class="section bg-white" aria-labelledby="milestones-heading">
            <div class="container-site max-w-4xl">
                <h2 id="milestones-heading" class="text-3xl font-semibold">Milestones</h2>
                <ol class="mt-10 space-y-8 border-l-2 border-brand-accent/40 pl-8">
                    @foreach ($milestones as $milestone)
                        <li class="relative">
                            <span aria-hidden="true" class="absolute top-1.5 -left-[2.4rem] h-3.5 w-3.5 rounded-full border-2 border-white bg-brand-accent"></span>
                            <p class="font-display text-xl font-semibold text-brand-secondary">{{ $milestone['year'] ?? '' }}</p>
                            <p class="mt-1 text-ink/80">{{ $milestone['text'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    @if ($affiliations->isNotEmpty() || Settings::get('about_governance'))
        <section class="section" aria-label="Affiliations and governance">
            <div class="container-site grid gap-12 lg:grid-cols-2">
                @if ($affiliations->isNotEmpty())
                    <div>
                        <h2 class="text-2xl font-semibold">Professional affiliations</h2>
                        <ul class="mt-6 space-y-3">
                            @foreach ($affiliations as $affiliation)
                                <li class="flex items-center gap-3">
                                    <span aria-hidden="true" class="h-2 w-2 rounded-full bg-brand-accent"></span>
                                    @if ($url = $safeUrl($affiliation['url'] ?? null))
                                        <a href="{{ $url }}" rel="noopener noreferrer" target="_blank" class="text-brand hover:underline">{{ $affiliation['name'] }}<span class="sr-only"> (opens in a new tab)</span></a>
                                    @else
                                        <span>{{ $affiliation['name'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if (Settings::get('about_governance'))
                    <div>
                        <h2 class="text-2xl font-semibold">Governance &amp; ethics</h2>
                        <x-site.rich :html="Settings::get('about_governance')" class="mt-6" />
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if ($offices->isNotEmpty())
        <section class="section bg-white" aria-labelledby="offices-heading">
            <div class="container-site">
                <h2 id="offices-heading" class="text-3xl font-semibold">Office locations</h2>
                <x-site.office-list :offices="$offices" class="mt-10" />
            </div>
        </section>
    @endif

    <x-site.cta />
</x-layouts.site>
