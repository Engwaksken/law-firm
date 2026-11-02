@php use App\Support\Money; use App\Support\Settings; $c = $invoice->currency; @endphp
@extends('pdf.layout')
@section('title', $invoice->displayNumber())
@section('content')
    @if ($draft)<div class="stamp">DRAFT</div>@endif
    @if ($invoice->status === 'void')<div class="stamp">VOID</div>@endif

    <table>
        <tr>
            <td style="width: 55%">
                <h1>{{ Settings::get('vat_registered') ? 'TAX INVOICE' : 'INVOICE' }}</h1>
                <div class="muted">Bill to</div>
                <strong>{{ $invoice->client->name }}</strong><br>
                @if ($invoice->client->address){{ $invoice->client->address }}<br>@endif
                {{ collect([$invoice->client->city, $invoice->client->country])->filter()->implode(', ') }}<br>
                @if ($invoice->client->email){{ $invoice->client->email }}@endif
            </td>
            <td class="box">
                <table>
                    <tr><td class="muted">Invoice no.</td><td class="num"><strong>{{ $invoice->displayNumber() }}</strong></td></tr>
                    <tr><td class="muted">Issue date</td><td class="num">{{ $invoice->issue_date?->format('d/m/Y') ?? '—' }}</td></tr>
                    <tr><td class="muted">Due date</td><td class="num">{{ $invoice->due_date?->format('d/m/Y') ?? '—' }}</td></tr>
                    <tr><td class="muted">Matter</td><td class="num">{{ $invoice->matter->reference }}</td></tr>
                    <tr><td class="muted">Client ref.</td><td class="num">{{ $invoice->client->reference }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top: 14px;">
        <thead><tr><th style="width: 52%">Description</th><th class="num">Qty / hrs</th><th class="num">Rate</th><th class="num">Tax</th><th class="num">Amount</th></tr></thead>
        <tbody>
        @foreach ($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="num">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                <td class="num">{{ Money::format($item->unit_price, $c) }}</td>
                <td class="num">{{ (float) $item->tax_rate > 0 ? rtrim(rtrim($item->tax_rate, '0'), '.').'%' : '—' }}</td>
                <td class="num">{{ Money::format($item->line_subtotal, $c) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table style="margin-top: 10px;"><tr><td style="width: 55%; vertical-align: top;">
        @if ($invoice->notes)<div class="muted">Notes</div><div>{!! nl2br(e($invoice->notes)) !!}</div>@endif
        @if (Settings::get('payment_instructions'))
            <div class="muted" style="margin-top: 8px;">How to pay</div>
            <div>{!! nl2br(e(Settings::get('payment_instructions'))) !!}</div>
            <div class="muted">Please quote {{ $invoice->displayNumber() }} with your payment.</div>
        @endif
    </td><td>
        <table class="totals">
            <tr><td>Subtotal</td><td class="num">{{ Money::format($invoice->subtotal, $c) }}</td></tr>
            @if (! Money::isZero($invoice->tax_total))<tr><td>VAT</td><td class="num">{{ Money::format($invoice->tax_total, $c) }}</td></tr>@endif
            <tr class="grand"><td>Total</td><td class="num">{{ Money::format($invoice->total, $c) }}</td></tr>
            @if (! Money::isZero($invoice->amount_paid))<tr><td>Paid</td><td class="num">−{{ Money::format($invoice->amount_paid, $c) }}</td></tr>@endif
            @if (! Money::isZero($invoice->amount_credited))<tr><td>Credited</td><td class="num">−{{ Money::format($invoice->amount_credited, $c) }}</td></tr>@endif
            <tr class="grand"><td>Balance due</td><td class="num">{{ Money::format($invoice->balance, $c) }}</td></tr>
        </table>
    </td></tr></table>
@endsection
