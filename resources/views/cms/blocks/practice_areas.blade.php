@php
    $areas = \App\Models\PracticeArea::query()->visible()
        ->when($data['featured_only'] ?? false, fn ($query) => $query->where('is_featured', true))
        ->take(max(1, min(24, (int) ($data['limit'] ?? 6))))->get();
@endphp
@if ($areas->isNotEmpty())
    <section @if ($anchor) id="{{ $anchor }}" @endif class="section bg-white">
        <div class="container-site">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    @if (filled($data['heading'] ?? null))<h2 class="text-3xl font-semibold sm:text-4xl">{{ $data['heading'] }}</h2>@endif
                    @if (filled($data['intro'] ?? null))<p class="mt-3 max-w-2xl text-ink/70">{{ $data['intro'] }}</p>@endif
                </div>
                <a href="{{ route('practice-areas.index') }}" class="text-sm font-semibold text-brand-secondary hover:underline">All practice areas →</a>
            </div>
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($areas as $area)<x-site.practice-card :area="$area" />@endforeach
            </div>
        </div>
    </section>
@endif
