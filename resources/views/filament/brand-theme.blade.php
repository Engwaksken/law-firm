@php
    use App\Support\Settings;

    $brandHex = fn (string $key, string $fallback) => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) Settings::get($key))
        ? Settings::get($key)
        : $fallback;
    $radius = ['square' => '0px', 'rounded' => '0.625rem', 'soft' => '1rem'][Settings::get('style_radius')] ?? '0.625rem';
    $shadow = ['none' => 'none', 'subtle' => '0 1px 3px rgb(0 0 0 / 0.10)', 'strong' => '0 8px 24px rgb(0 0 0 / 0.16)'][Settings::get('style_shadow')] ?? '0 1px 3px rgb(0 0 0 / 0.10)';
@endphp
<style>
    :root {
        --admin-brand-primary: {{ $brandHex('color_primary', '#2d3290') }};
        --admin-brand-secondary: {{ $brandHex('color_secondary', '#019bdb') }};
        --admin-brand-accent: {{ $brandHex('color_accent', '#5bb3db') }};
        --admin-brand-background: {{ $brandHex('color_background', '#fefff7') }};
        --admin-brand-text: {{ $brandHex('color_text', '#252525') }};
        --admin-brand-radius: {{ $radius }};
        --admin-brand-shadow: {{ $shadow }};
    }

    .fi-sidebar {
        background-color: var(--admin-brand-primary) !important;
        color: #fff !important;
    }
    .fi-sidebar :is(.fi-sidebar-header, .fi-sidebar-group-label, .fi-sidebar-item-label, .fi-sidebar-item-icon, .fi-icon) {
        color: rgb(255 255 255 / 0.88) !important;
    }
    .fi-sidebar .fi-sidebar-item-active > .fi-sidebar-item-button {
        background-color: rgb(255 255 255 / 0.16) !important;
        box-shadow: inset 3px 0 0 var(--admin-brand-accent);
    }
    .fi-sidebar .fi-sidebar-item-active :is(.fi-sidebar-item-label, .fi-sidebar-item-icon) {
        color: #fff !important;
    }
    .fi-sidebar .fi-sidebar-item-button:hover { background-color: rgb(255 255 255 / 0.10) !important; }

    .fi-wi-stats-overview-stat {
        min-height: 0 !important;
        padding: 0.9rem 1rem !important;
        border-radius: var(--admin-brand-radius) !important;
        box-shadow: var(--admin-brand-shadow) !important;
    }
    .fi-wi-stats-overview-stat .fi-wi-stats-overview-stat-content { gap: 0.25rem !important; }
    .fi-wi-stats-overview-stat .fi-wi-stats-overview-stat-value,
    .fi-wi-stats-overview-stat .fi-wi-stats-overview-stat-placeholder { font-size: 1.5rem !important; line-height: 1.8rem !important; }
    .fi-section, .fi-ta-ctn, .fi-modal-window { border-radius: var(--admin-brand-radius) !important; }

</style>
