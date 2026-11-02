<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Support\PortalAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/** Client PDF downloads: issued invoices, receipts, statements. 404 for anything not theirs. */
class PortalFinanceController extends Controller
{
    public function invoice(Request $request, Invoice $invoice): Response
    {
        abort_unless(PortalAccess::canSeeInvoice($request->user(), $invoice), 404);

        return $this->pdf()->invoice($invoice);
    }

    public function receipt(Request $request, Receipt $receipt): Response
    {
        abort_unless(PortalAccess::receipts($request->user())->whereKey($receipt->id)->exists(), 404);

        return $this->pdf()->receipt($receipt);
    }

    public function statement(Request $request, Client $client): Response
    {
        abort_unless(PortalAccess::hasClient($request->user(), $client->id), 404);

        $parse = fn (?string $date) => $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? Carbon::parse($date) : null;

        return $this->pdf()->statement($client, $parse($request->query('from')), $parse($request->query('to')));
    }

    private function pdf(): object
    {
        abort_unless(class_exists(\App\Finance\FinancePdf::class), 503, 'PDF documents are not available yet.');

        return app(\App\Finance\FinancePdf::class);
    }
}
