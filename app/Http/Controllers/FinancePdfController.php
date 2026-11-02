<?php

namespace App\Http\Controllers;

use App\Finance\FinancePdf;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/** Staff downloads of finance PDFs. Every request is authorised and audited. */
class FinancePdfController extends Controller
{
    public function __construct(private FinancePdf $pdf) {}

    public function invoice(Invoice $invoice): Response
    {
        Gate::authorize('downloadPdf', $invoice);
        ActivityLog::record('invoice.pdf', $invoice, 'Invoice PDF downloaded');

        return $this->pdf->invoice($invoice);
    }

    public function receipt(Receipt $receipt): Response
    {
        Gate::authorize('view', $receipt->payment);

        return $this->pdf->receipt($receipt);
    }

    public function creditNote(CreditNote $creditNote): Response
    {
        Gate::authorize('view', $creditNote->invoice);

        return $this->pdf->creditNote($creditNote);
    }

    public function statement(Request $request, Client $client): Response
    {
        abort_unless($request->user()->can('invoices.issue') || $request->user()->can('payments.record'), 403);
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'currency' => ['nullable', 'string', 'size:3']]);
        ActivityLog::record('client.statement', $client, 'Statement of account downloaded');

        return $this->pdf->statement(
            $client,
            filled($data['from'] ?? null) ? Carbon::parse($data['from']) : null,
            filled($data['to'] ?? null) ? Carbon::parse($data['to']) : null,
            $data['currency'] ?? null,
        );
    }
}
