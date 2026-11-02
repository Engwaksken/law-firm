@php use App\Support\Settings; $primary = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) Settings::get('color_primary')) ? Settings::get('color_primary') : '#14283F'; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { margin: 28mm 16mm 22mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #252525; }
        header { position: fixed; top: -22mm; left: 0; right: 0; }
        footer { position: fixed; bottom: -16mm; left: 0; right: 0; font-size: 8px; color: #666; border-top: 1px solid #ddd; padding-top: 4px; }
        h1 { font-size: 20px; margin: 0 0 4px; color: {{ $primary }}; letter-spacing: 1px; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; }
        .items th { background: {{ $primary }}; color: #fff; text-align: left; padding: 6px; font-size: 9px; text-transform: uppercase; }
        .items td { padding: 6px; border-bottom: 1px solid #eee; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals td { padding: 4px 6px; }
        .totals .grand td { font-weight: bold; font-size: 12px; border-top: 2px solid {{ $primary }}; }
        .box { border: 1px solid #ddd; padding: 8px; }
        .stamp { position: fixed; top: 35%; left: 15%; font-size: 90px; color: rgba(200, 0, 0, 0.12); transform: rotate(-30deg); }
    </style>
</head>
<body>
    <header>
        <table><tr>
            <td>
                @if ($logo)<img src="{{ $logo }}" style="height: 16mm;">@else<strong style="font-size: 14px; color: {{ $primary }}">{{ Settings::get('firm_name') }}</strong>@endif
            </td>
            <td class="num muted" style="font-size: 9px;">
                <strong style="color:#252525">{{ Settings::get('firm_name') }}</strong><br>
                {!! nl2br(e(Settings::get('contact_address'))) !!}<br>
                {{ Settings::get('contact_phone') }} · {{ Settings::get('contact_email') }}
                @if (Settings::get('firm_tin'))<br>TIN: {{ Settings::get('firm_tin') }}@endif
            </td>
        </tr></table>
    </header>
    <footer>
        {{ Settings::get('invoice_footer') ?? Settings::get('firm_name') }}
        <span style="float: right">Generated {{ now()->format('d/m/Y H:i') }}</span>
    </footer>
    @yield('content')
</body>
</html>
