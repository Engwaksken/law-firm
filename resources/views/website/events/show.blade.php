@php
    $cancelled = $event->status === 'cancelled';
    $registration = $cancelled || $event->isPast() ? null : \Illuminate\Support\Str::sanitizeUrl($event->registration_url);
    $imageUrl = $event->image?->url();
    $schema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $event->title,
        'description' => $event->summary ?: str(strip_tags((string) $event->description))->squish()->limit(300)->toString(),
        'startDate' => $event->starts_at->toIso8601String(),
        'endDate' => $event->ends_at?->toIso8601String(),
        'eventStatus' => $cancelled ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => $event->is_online ? 'https://schema.org/OnlineEventAttendanceMode' : 'https://schema.org/OfflineEventAttendanceMode',
        'location' => $event->is_online
            ? ['@type' => 'VirtualLocation', 'url' => $registration ?? route('events.show', $event)]
            : ($event->location ? ['@type' => 'Place', 'name' => $event->location, 'address' => $event->location] : null),
        'image' => $imageUrl,
        'organizer' => ['@type' => 'Organization', 'name' => \App\Support\Settings::get('firm_name'), 'url' => url('/')],
    ]);
@endphp
<x-layouts.site :title="$event->title" :description="$event->summary" :image="$imageUrl"
    :breadcrumbs="['Events' => route('events.index'), $event->title => null]">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    </x-slot:head>

    <x-site.page-hero eyebrow="Event" :title="$event->title" :lead="$event->summary">
        <dl class="mt-8 flex flex-wrap gap-x-10 gap-y-3 text-sm">
            <div><dt class="text-white/60">Date</dt><dd class="font-medium"><time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $event->starts_at->format('l j F Y') }}</time></dd></div>
            <div><dt class="text-white/60">Time</dt><dd class="font-medium">{{ $event->starts_at->format('H:i') }}@if ($event->ends_at) – {{ $event->ends_at->isSameDay($event->starts_at) ? $event->ends_at->format('H:i') : $event->ends_at->format('j M Y H:i') }}@endif</dd></div>
            @if ($event->location || $event->is_online)
                <div><dt class="text-white/60">Location</dt><dd class="font-medium">{{ collect([$event->is_online ? 'Online' : null, $event->location])->filter()->implode(' / ') }}</dd></div>
            @endif
        </dl>
    </x-site.page-hero>

    <section class="section">
        <div class="container-site grid gap-12 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @if ($cancelled)
                    <div class="mb-8 rounded-lg border border-red-200 bg-red-50 p-5 text-red-900" role="alert">
                        <p class="font-semibold">This event has been cancelled.</p>
                        <p class="mt-1 text-sm">We apologise for any inconvenience. Please see our other upcoming events.</p>
                    </div>
                @endif
                @if ($imageUrl)
                    <img src="{{ $imageUrl }}" alt="{{ $event->image->alt_text }}" class="mb-10 w-full rounded-lg">
                @endif
                <x-site.rich :html="$event->description" class="text-lg" />
            </div>
            <aside>
                <div class="card sticky top-28 p-6">
                    @if ($registration)
                        <h2 class="text-lg font-semibold">Attend this event</h2>
                        <a href="{{ $registration }}" target="_blank" rel="noopener noreferrer" class="btn-primary mt-4 w-full">Register<span class="sr-only"> (opens in a new tab)</span></a>
                    @elseif ($cancelled)
                        <h2 class="text-lg font-semibold">Event cancelled</h2>
                        <p class="mt-2 text-sm text-ink/70">Registration is closed.</p>
                    @elseif ($event->isPast())
                        <h2 class="text-lg font-semibold">This event has taken place</h2>
                    @else
                        <h2 class="text-lg font-semibold">Interested in attending?</h2>
                        <a href="{{ route('contact') }}" class="btn-primary mt-4 w-full">Contact us</a>
                    @endif
                    <a href="{{ route('events.index') }}" class="mt-4 block text-sm font-semibold text-brand-secondary hover:underline">← All events</a>
                </div>
            </aside>
        </div>
    </section>
</x-layouts.site>
