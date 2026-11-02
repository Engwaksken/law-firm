@php use App\Support\Money; $invoice = $note->invoice; @endphp
@extends('pdf.layout')
@section('title', $note->number)
@section('content')
    <h1>CREDIT NOTE</h1>
    <table style="margin-top: 10px;">
        <tr>
            <td style="width: 55%"><div class="muted">Client</div><strong>{{ $invoice->client->name }}</strong><br>{{ $invoice->client->reference }}</td>
            <td class="box"><table>
                <tr><td class="muted">Credit note no.</td><td class="num"><strong>{{ $note->number }}</strong></td></tr>
                <tr><td class="muted">Date</td><td class="num">{{ $note->issued_at->format('d/m/Y') }}</td></tr>
                <tr><td class="muted">Against invoice</td><td class="num">{{ $invoice->number }}</td></tr>
            </table></td>
        </tr>
    </table>
    <table class="items" style="margin-top: 16px;">
        <thead><tr><th>Reason</th><th class="num">Amount credited</th></tr></thead>
        <tbody><tr><td>{{ $note->reason }}</td><td class="num"><strong>{{ Money::format($note->amount, $invoice->currency) }}</strong></td></tr></tbody>
    </table>
@endsection
