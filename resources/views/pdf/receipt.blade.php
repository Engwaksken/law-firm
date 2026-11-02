@php use App\Support\Money; use App\Models\Payment; $invoice = $payment->invoice; @endphp
@extends('pdf.layout')
@section('title', $receipt->number)
@section('content')
    @if ($payment->isReversed())<div class="stamp">REVERSED</div>@endif
    <h1>RECEIPT</h1>
    <table style="margin-top: 10px;">
        <tr>
            <td style="width: 55%">
                <div class="muted">Received from</div>
                <strong>{{ $invoice->client->name }}</strong><br>{{ $invoice->client->reference }}
            </td>
            <td class="box">
                <table>
                    <tr><td class="muted">Receipt no.</td><td class="num"><strong>{{ $receipt->number }}</strong></td></tr>
                    <tr><td class="muted">Date received</td><td class="num">{{ $payment->received_on->format('d/m/Y') }}</td></tr>
                    <tr><td class="muted">Method</td><td class="num">{{ Payment::METHODS[$payment->method] ?? $payment->method }}</td></tr>
                    @if ($payment->reference)<tr><td class="muted">Reference</td><td class="num">{{ $payment->reference }}</td></tr>@endif
                </table>
            </td>
        </tr>
    </table>
    <table class="items" style="margin-top: 16px;">
        <thead><tr><th>Applied to</th><th class="num">Invoice total</th><th class="num">Amount received</th><th class="num">Balance after</th></tr></thead>
        <tbody><tr>
            <td>Invoice {{ $invoice->number }} · matter {{ $invoice->matter->reference }}</td>
            <td class="num">{{ Money::format($invoice->total, $payment->currency) }}</td>
            <td class="num"><strong>{{ Money::format($payment->amount, $payment->currency) }}</strong></td>
            <td class="num">{{ Money::format($invoice->balance, $payment->currency) }}</td>
        </tr></tbody>
    </table>
    <p class="muted" style="margin-top: 20px;">Thank you for your payment.</p>
@endsection
