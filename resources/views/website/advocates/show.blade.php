<x-layouts.site :title="$advocate->name" :description="str($advocate->bio)->limit(160)"
    :image="$advocate->photo_path ? Storage::disk('public')->url($advocate->photo_path) : null"
    :breadcrumbs="['Our advocates' => route('advocates.index'), $advocate->name => null]">

    <section class="bg-brand text-white">
        <div class="container-site grid items-end gap-10 py-14 md:grid-cols-[18rem_1fr]">
            <div class="aspect-[4/5] overflow-hidden rounded-lg bg-white/10">
                @if ($advocate->photo_path)
                    <img src="{{ Storage::disk('public')->url($advocate->photo_path) }}" alt="Portrait of {{ $advocate->name }}" class="h-full w-full object-cover">
                @else
                    <div aria-hidden="true" class="grid h-full place-items-center font-display text-6xl font-semibold text-brand-accent">
                        {{ str($advocate->name)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
                    </div>
                @endif
            </div>
            <div>
                <p class="text-xs font-semibold tracking-[0.2em] text-brand-accent uppercase">{{ $advocate->title ?? 'Advocate' }}</p>
                <h1 class="mt-3 text-4xl font-semibold text-white sm:text-5xl">{{ $advocate->name }}</h1>
                @if ($advocate->practiceAreas->isNotEmpty())
                    <ul class="mt-5 flex flex-wrap gap-2" aria-label="Areas of specialisation">
                        @foreach ($advocate->practiceAreas as $area)
                            <li><a href="{{ route('practice-areas.show', $area) }}" class="rounded-full border border-white/30 px-3 py-1 text-xs hover:border-brand-accent hover:text-brand-accent">{{ $area->name }}</a></li>
                        @endforeach
                    </ul>
                @endif
                <div class="mt-8 flex flex-wrap gap-3">
                    @if ($advocate->accepts_consultations)
                        <a href="{{ route('consultation', ['advocate' => $advocate->slug]) }}" class="btn-accent">Request a consultation</a>
                    @endif
                    {{-- Direct contact details only when the advocate opted in. --}}
                    @if ($advocate->show_email && $advocate->email)
                        <a href="mailto:{{ $advocate->email }}" class="btn-ghost-light">{{ $advocate->email }}</a>
                    @endif
                    @if ($advocate->show_phone && $advocate->phone)
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $advocate->phone) }}" class="btn-ghost-light">{{ $advocate->phone }}</a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container-site grid gap-12 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <h2 class="text-2xl font-semibold">Profile</h2>
                <x-site.rich :html="$advocate->bio" class="mt-4 text-lg" />
                @if ($advocate->experience)
                    <h2 class="mt-12 text-2xl font-semibold">Professional experience</h2>
                    <x-site.rich :html="$advocate->experience" class="mt-4" />
                @endif
            </div>
            <aside class="space-y-8">
                @if (filled($advocate->qualifications))
                    <div class="card p-6">
                        <h2 class="text-lg font-semibold">Qualifications</h2>
                        <ul class="mt-3 space-y-2 text-sm text-ink/80">
                            @foreach ($advocate->qualifications as $qualification)<li>{{ $qualification }}</li>@endforeach
                        </ul>
                    </div>
                @endif
                @if (filled($advocate->memberships))
                    <div class="card p-6">
                        <h2 class="text-lg font-semibold">Professional memberships</h2>
                        <ul class="mt-3 space-y-2 text-sm text-ink/80">
                            @foreach ($advocate->memberships as $membership)<li>{{ $membership }}</li>@endforeach
                        </ul>
                    </div>
                @endif
                @if ($advocate->office)
                    <div class="card p-6">
                        <h2 class="text-lg font-semibold">Office</h2>
                        <p class="mt-3 text-sm text-ink/80">{{ $advocate->office->name }}@if ($advocate->office->city), {{ $advocate->office->city }}@endif</p>
                    </div>
                @endif
                <p class="text-xs text-ink/50">Profile information verified by the firm on {{ $advocate->credentials_verified_at->format('j F Y') }}.</p>
            </aside>
        </div>
    </section>

    <x-site.cta />
</x-layouts.site>
