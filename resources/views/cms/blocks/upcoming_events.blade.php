@php $events = \App\Models\Event::query()->upcoming()->take(max(1, min(12, (int) ($data['limit'] ?? 3))))->get(); @endphp
@if ($events->isNotEmpty())
    <section @if ($anchor) id="{{ $anchor }}" @endif class="section bg-white">
        <div class="container-site">
            <div class="flex flex-wrap items-end justify-between gap-4">
                @if (filled($data['heading'] ?? null))<h2 class="text-3xl font-semibold sm:text-4xl">{{ $data['heading'] }}</h2>@endif
                <a href="{{ route('events.index') }}" class="text-sm font-semibold text-brand-secondary hover:underline">All events →</a>
            </div>
            <ul class="mt-10 grid gap-6 lg:grid-cols-3">
                @foreach ($events as $event)
                    <li class="card relative flex gap-5 p-6">
                        <div class="w-16 shrink-0 rounded bg-brand py-2 text-center text-white">
                            <span class="block text-xs uppercase">{{ $event->starts_at->format('M') }}</span>
                            <span class="block font-display text-2xl font-semibold">{{ $event->starts_at->format('j') }}</span>
                        </div>
                        <div>
                            <h3 class="font-semibold"><a href="{{ route('events.show', $event) }}" class="after:absolute after:inset-0">{{ $event->title }}</a></h3>
                            <p class="mt-1 text-sm text-ink/60">{{ $event->starts_at->format('g:i a') }} · {{ $event->is_online ? 'Online' : $event->location }}</p>
                            @if ($event->status === 'cancelled')<p class="mt-1 text-sm font-semibold text-red-700">Cancelled</p>@endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
