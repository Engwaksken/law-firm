<x-layouts.site title="Our advocates" description="Meet the advocates of the firm." :breadcrumbs="['Our advocates' => null]">
    <x-site.page-hero eyebrow="Our people" title="Our advocates" lead="Experienced advocates committed to clear advice and dedicated representation." />

    <section class="section">
        <div class="container-site">
            <form method="GET" action="{{ route('advocates.index') }}" role="search" class="mb-10 grid gap-4 rounded-lg bg-white p-5 shadow-sm sm:grid-cols-[1fr_1fr_auto]">
                <div>
                    <label for="q" class="form-label">Name</label>
                    <input id="q" name="q" type="search" value="{{ $search }}" class="form-input" placeholder="Search by name">
                </div>
                <div>
                    <label for="practice_area" class="form-label">Practice area</label>
                    <select id="practice_area" name="practice_area" class="form-input">
                        <option value="">All practice areas</option>
                        @foreach ($practiceAreas as $area)
                            <option value="{{ $area->slug }}" @selected($selectedArea?->id === $area->id)>{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary">Filter</button>
                    @if ($search || $selectedArea)<a href="{{ route('advocates.index') }}" class="btn-outline">Clear</a>@endif
                </div>
            </form>

            <p class="sr-only" role="status">{{ trans_choice(':count advocate found|:count advocates found', $advocates->count()) }}</p>

            @if ($advocates->isEmpty())
                <p class="text-ink/70">No advocates match your search.</p>
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($advocates as $advocate)
                        <x-site.advocate-card :advocate="$advocate" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-layouts.site>
