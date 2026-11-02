<x-layouts.site title="Events" description="Seminars, webinars and events hosted or attended by the firm." :breadcrumbs="['Events' => null]">
    <x-site.page-hero eyebrow="Events" title="Events" lead="Seminars, webinars and events where our advocates share their expertise." />

    <section class="section">
        <div class="container-site max-w-5xl">
            <h2 class="text-2xl font-semibold">Upcoming events</h2>
            @if ($upcoming->isEmpty())
                <p class="mt-4 text-ink/70">There are no upcoming events at the moment. Subscribe to our newsletter to hear about future events.</p>
            @else
                <div class="mt-6 grid gap-4">
                    @foreach ($upcoming as $event)
                        <x-site.event-card :event="$event" />
                    @endforeach
                </div>
            @endif

            @if ($past->isNotEmpty())
                <h2 class="mt-16 text-2xl font-semibold" id="past-events">Past events</h2>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    @foreach ($past as $event)
                        <x-site.event-card :event="$event" />
                    @endforeach
                </div>
                <div class="mt-8">{{ $past->fragment('past-events')->links() }}</div>
            @endif
        </div>
    </section>
</x-layouts.site>
