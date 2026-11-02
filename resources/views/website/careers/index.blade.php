<x-layouts.site title="Careers" description="Vacancies, internships and pupillage opportunities at the firm." :breadcrumbs="['Careers' => null]">
    <x-site.page-hero eyebrow="Join us" title="Careers"
        lead="We look for talented, principled people who want to do excellent legal work. See our current vacancies, internships and pupillage opportunities." />

    <section class="section">
        <div class="container-site max-w-5xl">
            @forelse ($groups as $group)
                <h2 class="mb-6 text-2xl font-semibold {{ $loop->first ? '' : 'mt-14' }}">{{ $group['label'] }}{{ $group['careers']->count() > 1 ? 's' : '' }}</h2>
                <ul class="space-y-4">
                    @foreach ($group['careers'] as $career)
                        <li class="card relative flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold"><a href="{{ route('careers.show', $career) }}" class="after:absolute after:inset-0">{{ $career->title }}</a></h3>
                                <p class="mt-1 text-sm text-ink/60">
                                    {{ collect([$career->employment_type, $career->location ?? $career->office?->city])->filter()->implode(' · ') }}
                                </p>
                                @if ($career->summary)<p class="mt-2 text-sm text-ink/80">{{ $career->summary }}</p>@endif
                            </div>
                            <p class="shrink-0 text-sm font-medium text-brand-secondary">
                                {{ $career->closes_at ? 'Closes '.$career->closes_at->format('j M Y') : 'Open until filled' }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            @empty
                <div class="card p-8 text-center">
                    <h2 class="text-xl font-semibold">No open positions at the moment</h2>
                    <p class="mt-2 text-ink/70">New opportunities are published here. Subscribe to our newsletter to hear about them.</p>
                    <div class="mx-auto mt-6 max-w-md"><livewire:newsletter-signup /></div>
                </div>
            @endforelse
        </div>
    </section>
</x-layouts.site>
