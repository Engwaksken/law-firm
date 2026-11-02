@php use App\Support\Money; @endphp
@extends('pdf.layout')
@section('title', 'Statement of account')
@section('content')
    <h1>STATEMENT OF ACCOUNT</h1>
    <table style="margin-top: 10px;">
        <tr>
            <td style="width: 55%"><div class="muted">Client</div><strong>{{ $client->name }}</strong><br>{{ $client->reference }}</td>
            <td class="box"><table>
                <tr><td class="muted">Period</td><td class="num">{{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}</td></tr>
                <tr><td class="muted">Currency</td><td class="num">{{ $currency }}</td></tr>
                <tr><td class="muted">Balance due</td><td class="num"><strong>{{ Money::format($closing, $currency) }}</strong></td></tr>
            </table></td>
        </tr>
    </table>
    <table class="items" style="margin-top: 16px;">
        <thead><tr><th>Date</th><th>Reference</th><th>Description</th><th class="num">Charges</th><th class="num">Credits</th><th class="num">Balance</th></tr></thead>
        <tbody>
            <tr><td>{{ $from->format('d/m/Y') }}</td><td></td><td>Opening balance</td><td></td><td></td><td class="num">{{ Money::format($opening, $currency) }}</td></tr>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line['date']->format('d/m/Y') }}</td>
                    <td>{{ $line['ref'] }}</td>
                    <td>{{ $line['description'] }}</td>
                    <td class="num">{{ $line['debit'] ? Money::format($line['debit'], $currency) : '' }}</td>
                    <td class="num">{{ $line['credit'] ? Money::format($line['credit'], $currency) : '' }}</td>
                    <td class="num">{{ Money::format($line['balance'], $currency) }}</td>
                </tr>
            @endforeach
            <tr class="grand"><td colspan="5"><strong>Closing balance</strong></td><td class="num"><strong>{{ Money::format($closing, $currency) }}</strong></td></tr>
        </tbody>
    </table>
@endsection
