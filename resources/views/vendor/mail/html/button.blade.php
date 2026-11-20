@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php
    use App\Support\Settings;

    $buttonColor = match ($color) {
        'success' => '#15803d',
        'error' => '#b91c1c',
        default => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) Settings::get('color_primary')) ? Settings::get('color_primary') : '#2d3290',
    };
@endphp
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 28px auto; text-align: {{ $align }};">
    <tr>
        <td align="{{ $align }}">
            <a href="{{ $url }}" target="_blank" rel="noopener" style="display: inline-block; padding: 12px 20px; border-radius: 8px; background-color: {{ $buttonColor }}; border: 1px solid {{ $buttonColor }}; color: #fff; font-size: 14px; font-weight: 700; text-decoration: none;">{!! $slot !!}</a>
        </td>
    </tr>
</table>
