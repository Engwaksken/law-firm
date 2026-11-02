<?php

namespace App\Finance;

use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Support\Money;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF rendering for finance documents. Callers are responsible for authorisation.
 * Remote resources are disabled in dompdf; the logo is embedded as a data URI.
 */
class FinancePdf
{
    public function invoice(Invoice $invoice): Response
    {
        $invoice->loadMissing(['items', 'client', 'matter', 'payments', 'creditNotes']);

        return $this->render('pdf.invoice', ['invoice' => $invoice, 'draft' => ! $invoice->isIssued() && $invoice->status !== 'void'],
            $invoice->displayNumber().'.pdf');
    }

    public function receipt(Receipt $receipt): Response
    {
        $receipt->loadMissing(['payment.invoice.client', 'payment.invoice.matter']);

        return $this->render('pdf.receipt', ['receipt' => $receipt, 'payment' => $receipt->payment], $receipt->number.'.pdf');
    }

    public function creditNote(CreditNote $note): Response
    {
        $note->loadMissing(['invoice.client', 'invoice.matter']);

        return $this->render('pdf.credit-note', ['note' => $note], $note->number.'.pdf');
    }

    /** Statement of account: opening balance, invoices, credits and payments in the period, closing balance. */
    public function statement(Client $client, ?Carbon $from = null, ?Carbon $to = null, ?string $currency = null): Response
    {
        return $this->render('pdf.statement', $this->statementData($client, $from, $to, $currency),
            'Statement-'.$client->reference.'-'.now()->format('Ymd').'.pdf');
    }

    /** @return array<string, mixed> */
    public function statementData(Client $client, ?Carbon $from = null, ?Carbon $to = null, ?string $currency = null): array
    {
        $to = ($to ?? today())->copy()->endOfDay();
        $from = ($from ?? $to->copy()->subMonths(3)->startOfMonth())->copy()->startOfDay();
        $currency ??= Settings::get('default_currency');

        $invoices = Invoice::query()->where('client_id', $client->id)->where('currency', $currency)->whereIn('status', Invoice::ISSUED_STATUSES);
        $payments = Payment::query()->where('client_id', $client->id)->where('currency', $currency)->whereNull('reversed_at');
        $credits = CreditNote::query()->whereHas('invoice', fn ($query) => $query->where('client_id', $client->id)->where('currency', $currency));

        $opening = Money::sub(
            Money::add(...(clone $invoices)->where('issue_date', '<', $from)->pluck('total')->all()),
            Money::add(
                ...(clone $payments)->where('received_on', '<', $from)->pluck('amount')->all(),
                ...(clone $credits)->where('issued_at', '<', $from)->pluck('amount')->all(),
            ),
        );

        $lines = collect()
            ->merge((clone $invoices)->whereBetween('issue_date', [$from, $to])->get()
                ->map(fn ($i) => ['date' => $i->issue_date, 'ref' => $i->number, 'description' => 'Invoice · '.$i->matter?->reference, 'debit' => $i->total, 'credit' => null]))
            ->merge((clone $credits)->whereBetween('issued_at', [$from, $to])->with('invoice')->get()
                ->map(fn ($c) => ['date' => $c->issued_at, 'ref' => $c->number, 'description' => 'Credit note on '.$c->invoice->number, 'debit' => null, 'credit' => $c->amount]))
            ->merge((clone $payments)->whereBetween('received_on', [$from, $to])->with(['invoice', 'receipt'])->get()
                ->map(fn ($p) => ['date' => $p->received_on, 'ref' => $p->receipt?->number, 'description' => 'Payment · '.(Payment::METHODS[$p->method] ?? $p->method).' · '.$p->invoice->number, 'debit' => null, 'credit' => $p->amount]))
            ->sortBy(fn ($l) => $l['date']->format('Y-m-d').($l['debit'] ? '0' : '1'))
            ->values();

        $running = $opening;
        $lines = $lines->map(function ($line) use (&$running) {
            $running = Money::sub(Money::add($running, $line['debit']), $line['credit']);

            return $line + ['balance' => $running];
        });

        return compact('client', 'from', 'to', 'currency', 'opening', 'lines') + ['closing' => $running];
    }

    private function render(string $view, array $data, string $filename): Response
    {
        $logo = Settings::get('logo_path');
        $data['logo'] = $logo && Storage::disk('public')->exists($logo)
            ? 'data:'.Storage::disk('public')->mimeType($logo).';base64,'.base64_encode(Storage::disk('public')->get($logo))
            : null;

        $pdf = Pdf::loadView($view, $data)->setPaper('a4')->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false]);

        return $pdf->download($filename)->withHeaders(['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }
}
