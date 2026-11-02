@php
    use App\Support\Settings;
    use App\Support\SiteNavigation;
    $items = SiteNavigation::header();
    $logo = Settings::get('logo_path');
    $firm = Settings::get('firm_name');
@endphp
<header x-data="{ open: false, mega: null }" @keydown.escape.window="open = false; mega = null"
        class="sticky top-0 z-50 border-b border-black/5 bg-white/95 backdrop-blur">
    <div class="container-site flex h-20 items-center justify-between gap-6">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3" aria-label="{{ $firm }}, home">
            @if ($logo)
                <img src="{{ Storage::disk('public')->url($logo) }}" alt="" class="h-11 w-auto">
            @else
                <span aria-hidden="true" class="grid h-11 w-11 place-items-center rounded bg-brand font-display text-lg font-bold text-brand-accent">
                    {{ str($firm)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
                </span>
            @endif
            <span class="font-display text-lg leading-tight font-semibold text-brand">{{ $firm }}</span>
        </a>

        {{-- Desktop navigation --}}
        <nav aria-label="Main" class="hidden lg:block">
            <ul class="flex items-center gap-1">
                @foreach ($items as $index => $item)
                    @php $isActive = $item['active'] && request()->routeIs($item['active']); @endphp
                    @if (! empty($item['children']))
                        <li class="relative" @mouseenter="mega = {{ $index }}" @mouseleave="mega = null">
                            <button type="button" @click="mega = mega === {{ $index }} ? null : {{ $index }}"
                                    :aria-expanded="(mega === {{ $index }}).toString()" aria-controls="nav-panel-{{ $index }}"
                                    class="inline-flex items-center gap-1 rounded px-3 py-2 text-sm font-medium {{ $isActive ? 'text-brand-secondary' : 'text-ink/80 hover:text-brand' }}">
                                {{ $item['label'] }}
                                <svg aria-hidden="true" class="h-4 w-4 transition" :class="mega === {{ $index }} && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                            </button>
                            <div id="nav-panel-{{ $index }}" x-show="mega === {{ $index }}" x-transition.opacity x-cloak
                                 class="{{ ! empty($item['mega']) ? 'fixed inset-x-0 top-20' : 'absolute left-0 top-full w-64' }} border-t border-black/5 bg-white shadow-xl">
                                <div class="{{ ! empty($item['mega']) ? 'container-site grid gap-x-8 gap-y-1 py-8 sm:grid-cols-2 lg:grid-cols-3' : 'p-2' }}">
                                    @if (! empty($item['mega']))
                                        <div class="lg:col-span-3 mb-4 flex items-end justify-between border-b border-black/5 pb-4">
                                            <p class="font-display text-xl font-semibold text-brand">{{ $item['label'] }}</p>
                                            <a href="{{ $item['url'] }}" class="text-sm font-semibold text-brand-secondary hover:underline">View all practice areas →</a>
                                        </div>
                                    @endif
                                    @foreach ($item['children'] as $child)
                                        <a href="{{ $child['url'] }}" class="block rounded p-3 hover:bg-canvas">
                                            <span class="block text-sm font-semibold text-brand">{{ $child['label'] }}</span>
                                            @if (! empty($child['description']))
                                                <span class="mt-0.5 line-clamp-2 block text-xs text-ink/60">{{ $child['description'] }}</span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </li>
                    @else
                        <li>
                            <a href="{{ $item['url'] }}" @if ($isActive) aria-current="page" @endif
                               class="rounded px-3 py-2 text-sm font-medium {{ $isActive ? 'text-brand-secondary' : 'text-ink/80 hover:text-brand' }}">
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>

        <div class="flex items-center gap-3">
            <a href="{{ route('consultation') }}" class="btn-primary hidden sm:inline-flex">Book a consultation</a>
            <button type="button" class="rounded p-2 text-brand lg:hidden" @click="open = !open"
                    :aria-expanded="open.toString()" aria-controls="mobile-nav">
                <span class="sr-only">Menu</span>
                <svg x-show="!open" aria-hidden="true" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                <svg x-show="open" x-cloak aria-hidden="true" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    {{-- Mobile navigation --}}
    <nav id="mobile-nav" aria-label="Main" x-show="open" x-collapse x-cloak class="border-t border-black/5 bg-white lg:hidden">
        <ul class="container-site space-y-1 py-4">
            @foreach ($items as $index => $item)
                <li x-data="{ sub: false }">
                    @if (! empty($item['children']))
                        <button type="button" @click="sub = !sub" :aria-expanded="sub.toString()"
                                class="flex w-full items-center justify-between rounded px-3 py-3 text-left font-medium text-brand">
                            {{ $item['label'] }}
                            <svg aria-hidden="true" class="h-5 w-5 transition" :class="sub && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                        </button>
                        <ul x-show="sub" x-cloak class="mb-2 ml-3 border-l border-black/10 pl-3">
                            <li><a href="{{ $item['url'] }}" class="block px-3 py-2 text-sm font-semibold text-brand-secondary">All {{ strtolower($item['label']) }}</a></li>
                            @foreach ($item['children'] as $child)
                                <li><a href="{{ $child['url'] }}" class="block px-3 py-2 text-sm text-ink/80">{{ $child['label'] }}</a></li>
                            @endforeach
                        </ul>
                    @else
                        <a href="{{ $item['url'] }}" class="block rounded px-3 py-3 font-medium text-brand">{{ $item['label'] }}</a>
                    @endif
                </li>
            @endforeach
            <li class="pt-2"><a href="{{ route('consultation') }}" class="btn-primary w-full">Book a consultation</a></li>
        </ul>
    </nav>
</header>
