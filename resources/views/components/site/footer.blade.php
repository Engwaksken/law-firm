@php
    use App\Support\Settings;
    $firm = Settings::get('firm_name');
    $logo = Settings::get('logo_dark_path') ?? Settings::get('logo_path');
    $areas = \App\Support\SiteNavigation::practiceAreaLinks();
    $offices = rescue(fn () => \App\Models\Office::where('is_active', true)->orderByDesc('is_head_office')->orderBy('name')->get(), collect(), false);
@endphp
<footer class="bg-brand text-white/80">
    <div class="container-site grid gap-12 py-16 md:grid-cols-2 lg:grid-cols-4">
        <div>
            @if ($logo)
                <img src="{{ Storage::disk('public')->url($logo) }}" alt="{{ $firm }}" class="mb-4 h-12 w-auto">
            @else
                <p class="font-display text-xl font-semibold text-white">{{ $firm }}</p>
            @endif
            <p class="mt-3 text-sm">{{ Settings::get('firm_tagline') }}</p>
            @if (Settings::get('footer_text'))
                <p class="mt-4 text-sm leading-relaxed">{{ Settings::get('footer_text') }}</p>
            @endif
            @if ($social = Settings::get('social_links'))
                <ul class="mt-6 flex flex-wrap gap-3" aria-label="Social media">
                    @foreach ($social as $network => $url)
                        <li><a href="{{ $url }}" rel="noopener noreferrer" target="_blank" class="rounded border border-white/20 px-3 py-1.5 text-xs hover:border-brand-accent hover:text-brand-accent">{{ $network }}<span class="sr-only"> (opens in a new tab)</span></a></li>
                    @endforeach
                </ul>
            @endif
        </div>

        <nav aria-label="Practice areas">
            <h2 class="mb-4 font-display text-sm font-semibold tracking-wider text-brand-accent uppercase">Practice areas</h2>
            <ul class="space-y-2 text-sm">
                @foreach (array_slice($areas, 0, 7) as $area)
                    <li><a href="{{ $area['url'] }}" class="hover:text-white">{{ $area['label'] }}</a></li>
                @endforeach
                <li><a href="{{ route('practice-areas.index') }}" class="font-semibold text-white hover:underline">All practice areas</a></li>
            </ul>
        </nav>

        <nav aria-label="Firm">
            <h2 class="mb-4 font-display text-sm font-semibold tracking-wider text-brand-accent uppercase">The firm</h2>
            <ul class="space-y-2 text-sm">
                <li><a href="{{ route('about') }}" class="hover:text-white">About us</a></li>
                <li><a href="{{ route('advocates.index') }}" class="hover:text-white">Our advocates</a></li>
                <li><a href="{{ route('insights.index') }}" class="hover:text-white">Legal insights</a></li>
                <li><a href="{{ route('careers.index') }}" class="hover:text-white">Careers</a></li>
                <li><a href="{{ route('consultation') }}" class="hover:text-white">Book a consultation</a></li>
                <li><a href="{{ route('privacy') }}" class="hover:text-white">Privacy notice</a></li>
            </ul>
        </nav>

        <div>
            <h2 class="mb-4 font-display text-sm font-semibold tracking-wider text-brand-accent uppercase">Contact</h2>
            <address class="space-y-2 text-sm not-italic">
                @if (Settings::get('contact_address'))<p class="whitespace-pre-line">{{ Settings::get('contact_address') }}</p>@endif
                @if ($phone = Settings::get('contact_phone'))<p><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="hover:text-white">{{ $phone }}</a></p>@endif
                @if ($email = Settings::get('contact_email'))<p><a href="mailto:{{ $email }}" class="hover:text-white">{{ $email }}</a></p>@endif
                @if (Settings::get('office_hours'))<p>{{ Settings::get('office_hours') }}</p>@endif
            </address>
            @if ($offices->count() > 1)
                <p class="mt-4 text-sm"><a href="{{ route('contact') }}#offices" class="font-semibold text-white hover:underline">{{ $offices->count() }} office locations</a></p>
            @endif
            <div class="mt-6"><livewire:newsletter-signup variant="dark" /></div>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="container-site flex flex-col gap-3 py-6 text-xs text-white/60 md:flex-row md:items-center md:justify-between">
            <p>&copy; {{ now()->year }} {{ $firm }}. All rights reserved.</p>
            <p class="max-w-2xl md:text-right">The content of this website is general information, not legal advice.</p>
        </div>
    </div>
</footer>
