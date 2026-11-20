@props(['url'])
@php
    use App\Support\Settings;
    use Illuminate\Support\Facades\Storage;

    $firm = Settings::get('firm_name');
    $logo = Settings::get('logo_path');
    $primary = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) Settings::get('color_primary')) ? Settings::get('color_primary') : '#2d3290';
@endphp
<tr>
    <td class="header" style="padding: 24px 16px; text-align: center;">
        <a href="{{ $url }}" style="display: inline-block; color: {{ $primary }}; font-size: 20px; font-weight: 700; text-decoration: none;">
            @if ($logo)
                <img src="{{ Storage::disk('public')->url($logo) }}" alt="{{ $firm }}" style="display: block; max-height: 64px; max-width: 240px; margin: 0 auto;">
                <span style="display: block; margin-top: 8px;">{{ $firm }}</span>
            @else
                {{ $firm }}
            @endif
        </a>
    </td>
</tr>
