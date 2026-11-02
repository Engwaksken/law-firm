@php
    $advocates = \App\Models\Advocate::query()->published()->with('practiceAreas')
        ->when($data['featured_only'] ?? false, fn ($query) => $query->where('is_featured', true))
        ->take(max(1, min(24, (int) ($data['limit'] ?? 4))))->get();
@endphp
@if ($advocates->isNotEmpty())
    <section @if ($anchor) id="{{ $anchor }}" @endif class="section">
        <div class="container-site">
            <div class="flex flex-wrap items-end justify-between gap-4">
                @if (filled($data['heading'] ?? null))<h2 class="text-3xl font-semibold sm:text-4xl">{{ $data['heading'] }}</h2>@endif
                <a href="{{ route('advocates.index') }}" class="text-sm font-semibold text-brand-secondary hover:underline">Meet the team →</a>
            </div>
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($advocates as $advocate)<x-site.advocate-card :advocate="$advocate" />@endforeach
            </div>
        </div>
    </section>
@endif
