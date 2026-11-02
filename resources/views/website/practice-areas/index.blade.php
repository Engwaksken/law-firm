<x-layouts.site title="Practice areas" description="The areas of law in which our advocates advise and represent clients." :breadcrumbs="['Practice areas' => null]">
    <x-site.page-hero eyebrow="What we do" title="Practice areas"
        lead="Our advocates advise and represent individuals, families and organisations across the following areas of law." />

    <section class="section">
        <div class="container-site">
            @if ($practiceAreas->isEmpty())
                <p class="text-ink/70">Details of our practice areas will be available shortly. In the meantime, please <a href="{{ route('contact') }}" class="font-semibold text-brand-secondary underline">contact us</a>.</p>
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($practiceAreas as $area)
                        <x-site.practice-card :area="$area" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <x-site.cta title="Not sure which area applies?" text="Describe your situation and we will direct you to the right advocate." />
</x-layouts.site>
