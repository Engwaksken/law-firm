<x-layouts.site :title="$career->title" :description="$career->summary" :breadcrumbs="['Careers' => route('careers.index'), $career->title => null]">
    <x-site.page-hero :eyebrow="$career->typeLabel()" :title="$career->title" :lead="$career->summary">
        <dl class="mt-8 flex flex-wrap gap-x-10 gap-y-3 text-sm">
            @if ($career->employment_type)<div><dt class="text-white/60">Type</dt><dd class="font-medium">{{ $career->employment_type }}</dd></div>@endif
            @if ($career->location ?? $career->office)<div><dt class="text-white/60">Location</dt><dd class="font-medium">{{ $career->location ?? $career->office->name }}</dd></div>@endif
            @if ($career->department)<div><dt class="text-white/60">Department</dt><dd class="font-medium">{{ $career->department->name }}</dd></div>@endif
            <div><dt class="text-white/60">Closing date</dt><dd class="font-medium">{{ $career->closes_at?->format('j F Y') ?? 'Open until filled' }}</dd></div>
        </dl>
    </x-site.page-hero>

    <section class="section">
        <div class="container-site grid gap-12 lg:grid-cols-5">
            <div class="lg:col-span-3">
                <h2 class="text-2xl font-semibold">About the role</h2>
                <x-site.rich :html="$career->description" class="mt-4" />
                @if ($career->requirements)
                    <h2 class="mt-10 text-2xl font-semibold">Requirements</h2>
                    <x-site.rich :html="$career->requirements" class="mt-4" />
                @endif
            </div>
            <div class="lg:col-span-2">
                <div class="card sticky top-28 p-6">
                    @if ($career->isOpen())
                        <h2 class="text-xl font-semibold">Apply for this position</h2>
                        <div class="mt-5"><livewire:career-application-form :career="$career" /></div>
                    @else
                        <h2 class="text-xl font-semibold">Applications closed</h2>
                        <p class="mt-2 text-sm text-ink/70">This position is no longer accepting applications. <a href="{{ route('careers.index') }}" class="font-semibold text-brand-secondary underline">See current opportunities</a>.</p>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>
