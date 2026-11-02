@props(['event'])
<article class="card group relative flex gap-5 p-5">
    <div class="flex h-20 w-20 shrink-0 flex-col items-center justify-center rounded-md {{ $event->isPast() ? 'bg-black/5 text-ink/60' : 'bg-brand text-white' }}" aria-hidden="true">
        <span class="text-xs font-semibold tracking-wider uppercase {{ $event->isPast() ? '' : 'text-brand-accent' }}">{{ $event->starts_at->format('M') }}</span>
        <span class="font-display text-3xl leading-none font-semibold">{{ $event->starts_at->format('j') }}</span>
        <span class="text-[0.65rem]">{{ $event->starts_at->format('Y') }}</span>
    </div>
    <div class="min-w-0">
        @if ($event->status === 'cancelled')
            <p class="mb-1 inline-block rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">Cancelled</p>
        @endif
        <h3 class="text-lg font-semibold leading-snug">
            <a href="{{ route('events.show', $event) }}" class="after:absolute after:inset-0">{{ $event->title }}</a>
        </h3>
        <p class="mt-1 text-sm text-ink/60">
            <time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $event->starts_at->format('l j F Y, H:i') }}</time>
            @if ($event->location || $event->is_online) · {{ $event->is_online ? 'Online' : '' }}{{ $event->is_online && $event->location ? ' / ' : '' }}{{ $event->location }}@endif
        </p>
        @if ($event->summary)<p class="mt-2 line-clamp-2 text-sm text-ink/80">{{ $event->summary }}</p>@endif
    </div>
</article>
