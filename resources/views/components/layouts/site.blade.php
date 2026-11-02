@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'breadcrumbs' => [],
    'noindex' => false,
])
@php
    use App\Support\Settings;
    $firm = Settings::get('firm_name');
    $pageTitle = $title ? "{$title} | {$firm}" : $firm.' | '.Settings::get('firm_tagline');
    $metaDescription = str($description ?: (Settings::get('seo_default_description') ?? Settings::get('home_hero_subtitle')))->stripTags()->squish()->limit(160);
    $image ??= ($default = Settings::get('seo_default_image')) ? Storage::disk('public')->url($default) : null;
    $noindex = $noindex || Settings::get('seo_discourage_indexing');
    $isHome = request()->routeIs('home');
    $hex = fn (string $key) => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) Settings::get($key)) ? Settings::get($key) : Settings::DEFINITIONS[$key]['default'];
    $fontHeading = Settings::get('font_heading');
    $fontBody = Settings::get('font_body');
    $fontQuery = collect([$fontHeading, $fontBody])->unique()
        ->map(fn ($f) => str($f)->lower()->replace(' ', '-').':400,500,600,700')->implode('|');
    $favicon = Settings::get('favicon_path');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @if ($noindex)<meta name="robots" content="noindex, nofollow">@endif
    @if ($v = Settings::get('seo_google_verification'))<meta name="google-site-verification" content="{{ $v }}">@endif
    @if ($v = Settings::get('seo_bing_verification'))<meta name="msvalidate.01" content="{{ $v }}">@endif
    <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $firm }}">
    <meta property="og:title" content="{{ $title ?? $firm }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($image)<meta property="og:image" content="{{ $image }}">@endif
    <link rel="icon" href="{{ $favicon ? Storage::disk('public')->url($favicon) : asset('favicon.ico') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family={{ $fontQuery }}&display=swap">

    <style>
        :root {
            --brand-primary: {{ $hex('color_primary') }};
            --brand-secondary: {{ $hex('color_secondary') }};
            --brand-accent: {{ $hex('color_accent') }};
            --brand-background: {{ $hex('color_background') }};
            --brand-text: {{ $hex('color_text') }};
            --brand-font-heading: '{{ e($fontHeading) }}';
            --brand-font-body: '{{ e($fontBody) }}';
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @if ($isHome && Settings::get('seo_organization_schema'))
        <script type="application/ld+json">{!! json_encode(array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'LegalService',
            'name' => $firm,
            'url' => url('/'),
            'logo' => ($logo = Settings::get('logo_path')) ? Storage::disk('public')->url($logo) : null,
            'description' => $metaDescription->toString(),
            'telephone' => Settings::get('contact_phone'),
            'email' => Settings::get('contact_email'),
            'address' => Settings::get('contact_address'),
            'sameAs' => array_values(Settings::get('social_links') ?: []) ?: null,
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endif
    @stack('jsonld')
    {{ $head ?? '' }}
</head>
<body class="flex min-h-screen flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] focus:rounded focus:bg-white focus:px-4 focus:py-2 focus:text-brand focus:shadow">
        Skip to main content
    </a>

    <x-site.header />

    <main id="main" class="flex-1" tabindex="-1">
        @if (count($breadcrumbs))
            <x-site.breadcrumbs :items="$breadcrumbs" />
        @endif
        {{ $slot }}
    </main>

    <x-site.footer />

    @livewireScripts
</body>
</html>
