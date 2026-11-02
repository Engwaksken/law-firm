<?php

namespace App\Finance;

use App\Events\InvoiceIssued;
use App\Models\ActivityLog;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Matter;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * All billing state changes go through here so that totals, statuses, numbering,
 * locking and the audit trail stay consistent.
 *
 *   draft ─submit─▶ pending_approval ─approve─▶ issued ─payments─▶ partially_paid ─▶ paid
 *     ▲                    │ reject                 │
 *     └────────────────────┘                        └─void (no payments)─▶ void
 *   Issued invoices are corrected with credit notes, never edited.
 */
class InvoiceService
{
    public function __construct(private DocumentNumbers $numbers) {}

    // ------------------------------------------------------------------ drafting

    /**
     * Create a draft from the matter's unbilled billable time and approved billable expenses.
     *
     * @param  array{up_to?: ?string, include_time?: bool, include_expenses?: bool, fixed_fee?: bool}  $options
     */
    public function draftFromUnbilled(Matter $matter, User $by, array $options = []): Invoice
    {
        $this->authorize(Gate::forUser($by)->allows('createFor', [Invoice::class, $matter]), 'You may not invoice this matter.');

        $upTo = filled($options['up_to'] ?? null) ? Carbon::parse($options['up_to'])->endOfDay() : null;
        $engagement = $matter->engagements()->where('status', 'approved')->latest('approved_at')->first();
        $currency = $engagement?->currency ?? Settings::get('default_currency');
        $taxRate = $this->feeTaxRate();

        return DB::transaction(function () use ($matter, $by, $options, $upTo, $engagement, $currency, $taxRate) {
            $invoice = new Invoice(['matter_id' => $matter->id, 'client_id' => $matter->client_id, 'currency' => $currency]);
            $invoice->created_by = $by->id;
            $invoice->save();
            $order = 0;

            if (($options['fixed_fee'] ?? false) && $engagement?->fee_arrangement === 'fixed' && $engagement->fixed_fee) {
                $invoice->items()->create(['type' => 'fee', 'description' => 'Professional fees: '.str($engagement->scope)->limit(200), 'quantity' => 1, 'unit_price' => $engagement->fixed_fee, 'tax_rate' => $taxRate, 'sort_order' => $order++]);
            }

            if ($options['include_time'] ?? true) {
                $entries = TimeEntry::query()->where('matter_id', $matter->id)->where('status', 'unbilled')->where('billable', true)
                    ->where('currency', $currency)
                    ->when($upTo, fn ($query) => $query->where('worked_on', '<=', $upTo))
                    ->with('user')->orderBy('worked_on')->lockForUpdate()->get();

                foreach ($entries as $entry) {
                    $item = $invoice->items()->create([
                        'type' => 'time',
                        'description' => $entry->worked_on->format('d/m/Y').' · '.$entry->user->name.' · '.$entry->description,
                        'quantity' => bcdiv((string) $entry->minutes, '60', 2),
                        'unit_price' => $entry->rate,
                        'tax_rate' => $taxRate,
                        'sort_order' => $order++,
                    ]);
                    // Use the exact recorded amount (minutes-based), not the rounded hours × rate.
                    $item->forceFill([
                        'line_subtotal' => $entry->amount,
                        'line_tax' => Money::percent($entry->amount, $taxRate),
                        'line_total' => Money::add($entry->amount, Money::percent($entry->amount, $taxRate)),
                    ])->saveQuietly();
                    $entry->forceFill(['status' => 'billed', 'invoice_item_id' => $item->id])->saveQuietly();
                }
            }

            if ($options['include_expenses'] ?? true) {
                $expenses = Expense::query()->where('matter_id', $matter->id)->where('status', 'approved')->where('billable', true)
                    ->where('currency', $currency)
                    ->when($upTo, fn ($query) => $query->where('incurred_on', '<=', $upTo))
                    ->orderBy('incurred_on')->lockForUpdate()->get();

                foreach ($expenses as $expense) {
                    // Disbursements are re-charged at cost without VAT.
                    $item = $invoice->items()->create([
                        'type' => 'expense',
                        'description' => $expense->incurred_on->format('d/m/Y').' · '.(Expense::CATEGORIES[$expense->category] ?? $expense->category).' · '.$expense->description,
                        'quantity' => 1, 'unit_price' => $expense->amount, 'tax_rate' => 0, 'sort_order' => $order++,
                    ]);
                    $expense->forceFill(['status' => 'billed', 'invoice_item_id' => $item->id])->saveQuietly();
                }
            }

            $this->recalculate($invoice);
            ActivityLog::record('invoice.drafted', $invoice, "Draft invoice for {$matter->reference}: ".Money::format($invoice->total, $invoice->currency));

            return $invoice;
        });
    }

    public function addItem(Invoice $invoice, User $by, array $data): InvoiceItem
    {
        $this->authorize(Gate::forUser($by)->allows('update', $invoice), 'This invoice can no longer be edited.');

        $type = $data['type'] ?? 'fee';
        $taxRate = $data['tax_rate'] ?? ($type === 'expense' ? '0' : $this->feeTaxRate());

        return DB::transaction(function () use ($invoice, $data, $type, $taxRate) {
            $item = $invoice->items()->create([
                'type' => $type,
                'description' => $data['description'],
                'quantity' => $data['quantity'] ?? 1,
                'unit_price' => $data['unit_price'],
                'tax_rate' => $taxRate,
                'sort_order' => ($invoice->items()->max('sort_order') ?? 0) + 1,
            ]);
            $this->recalculate($invoice);

            return $item;
        });
    }

    /** Removing a line releases the linked time/expenses back to unbilled. */
    public function removeItem(InvoiceItem $item, User $by): void
    {
        $invoice = $item->invoice;
        $this->authorize(Gate::forUser($by)->allows('update', $invoice), 'This invoice can no longer be edited.');

        DB::transaction(function () use ($item, $invoice) {
            $this->release($item);
            $item->delete();
            $this->recalculate($invoice);
        });
    }

    /** Draft deletion releases everything it contained. */
    public function deleteDraft(Invoice $invoice, User $by): void
    {
        $this->authorize(Gate::forUser($by)->allows('delete', $invoice), 'Only drafts can be deleted.');

        DB::transaction(function () use ($invoice) {
            $invoice->items->each(fn (InvoiceItem $item) => $this->release($item));
            ActivityLog::record('invoice.draft-deleted', $invoice, 'Draft invoice deleted');
            $invoice->items()->delete();
            $invoice->delete();
        });
    }

    // ------------------------------------------------------------------ approval

    public function submit(Invoice $invoice, User $by): void
    {
        $this->authorize(Gate::forUser($by)->allows('update', $invoice), 'You may not submit this invoice.');
        if ($invoice->items()->doesntExist() || Money::cmp($invoice->total, 0) <= 0) {
            throw ValidationException::withMessages(['invoice' => 'An invoice needs at least one line and a positive total.']);
        }

        $invoice->forceFill(['status' => 'pending_approval', 'submitted_by' => $by->id])->save();
        ActivityLog::record('invoice.submitted', $invoice, 'Invoice submitted for approval');
    }

    public function reject(Invoice $invoice, User $by, string $reason): void
    {
        $this->authorize(Gate::forUser($by)->allows('approve', $invoice), 'You may not review this invoice.');

        $invoice->forceFill([
            'status' => 'draft',
            'internal_notes' => trim(($invoice->internal_notes ? $invoice->internal_notes."\n" : '').'Returned by '.$by->name.' on '.now()->format('d/m/Y').': '.$reason),
        ])->save();
        ActivityLog::record('invoice.rejected', $invoice, 'Invoice returned to draft');
    }

    /** Approve and issue: allocates the invoice number and locks the invoice. */
    public function approve(Invoice $invoice, User $by, ?Carbon $issueDate = null, ?Carbon $dueDate = null): Invoice
    {
        $this->authorize(Gate::forUser($by)->allows('approve', $invoice), 'You may not approve this invoice.');

        DB::transaction(function () use ($invoice, $by, $issueDate, $dueDate) {
            $invoice->refresh();
            $this->recalculate($invoice);
            $issue = $issueDate ?? today();
            $invoice->forceFill([
                'number' => $this->numbers->next('INV', (int) $issue->format('Y')),
                'status' => 'issued',
                'issue_date' => $issue,
                'due_date' => $dueDate ?? $invoice->due_date ?? $issue->copy()->addDays((int) Settings::get('invoice_payment_terms_days')),
                'approved_by' => $by->id,
                'issued_at' => now(),
            ])->save();
            ActivityLog::record('invoice.issued', $invoice, "Invoice {$invoice->number} issued: ".Money::format($invoice->total, $invoice->currency));
        });

        event(new InvoiceIssued($invoice));

        return $invoice;
    }

    /** Only issued invoices without (unreversed) payments can be voided; their work becomes unbilled again. */
    public function void(Invoice $invoice, User $by, string $reason): void
    {
        $this->authorize(Gate::forUser($by)->allows('void', $invoice), 'You may not void this invoice.');
        if ($invoice->payments()->whereNull('reversed_at')->exists() || $invoice->creditNotes()->exists()) {
            throw ValidationException::withMessages(['invoice' => 'Invoices with payments or credit notes cannot be voided. Issue a credit note instead.']);
        }

        DB::transaction(function () use ($invoice, $by, $reason) {
            $invoice->items->each(fn (InvoiceItem $item) => $this->release($item));
            $invoice->forceFill(['status' => 'void', 'voided_at' => now(), 'void_reason' => $reason, 'balance' => '0.00'])->save();
            ActivityLog::record('invoice.voided', $invoice, "Invoice {$invoice->displayNumber()} voided: {$reason}", userId: $by->id);
        });
    }

    public function creditNote(Invoice $invoice, User $by, string $amount, string $reason): CreditNote
    {
        $this->authorize(Gate::forUser($by)->allows('credit', $invoice), 'You may not issue credit notes for this invoice.');
        if (Money::cmp($amount, 0) <= 0 || Money::cmp($amount, $invoice->balance) > 0) {
            throw ValidationException::withMessages(['amount' => 'The credit must be more than zero and not exceed the outstanding balance ('.Money::format($invoice->balance, $invoice->currency).').']);
        }

        return DB::transaction(function () use ($invoice, $by, $amount, $reason) {
            $note = CreditNote::create([
                'number' => $this->numbers->next('CRN'),
                'invoice_id' => $invoice->id, 'amount' => $amount, 'reason' => $reason,
                'issued_by' => $by->id, 'issued_at' => now(),
            ]);
            $this->recalculate($invoice);
            ActivityLog::record('invoice.credited', $invoice, "Credit note {$note->number} for ".Money::format($amount, $invoice->currency));

            return $note;
        });
    }

    // ------------------------------------------------------------------ payments

    /**
     * Records firm revenue against an issued invoice and issues a receipt.
     * Overpayments are refused: excess client money is not firm revenue (spec §12).
     *
     * @param  array{amount: string, method: string, reference?: ?string, received_on?: ?string, notes?: ?string, currency?: ?string}  $data
     */
    public function recordPayment(Invoice $invoice, User $by, array $data): Payment
    {
        $this->authorize(Gate::forUser($by)->allows('recordPayment', $invoice), 'You may not record payments for this invoice.');

        $amount = (string) $data['amount'];
        if (($data['currency'] ?? $invoice->currency) !== $invoice->currency) {
            throw ValidationException::withMessages(['currency' => "Payments must be in the invoice currency ({$invoice->currency})."]);
        }
        if (Money::cmp($amount, 0) <= 0) {
            throw ValidationException::withMessages(['amount' => 'The amount must be greater than zero.']);
        }
        if (Money::cmp($amount, $invoice->balance) > 0) {
            throw ValidationException::withMessages(['amount' => 'The amount exceeds the outstanding balance ('.Money::format($invoice->balance, $invoice->currency).'). Excess client funds must not be recorded as firm revenue.']);
        }
        if (! array_key_exists($data['method'], Payment::METHODS)) {
            throw ValidationException::withMessages(['method' => 'Choose a valid payment method.']);
        }

        return DB::transaction(function () use ($invoice, $by, $data, $amount) {
            $payment = new Payment([
                'invoice_id' => $invoice->id, 'client_id' => $invoice->client_id, 'amount' => $amount,
                'currency' => $invoice->currency, 'method' => $data['method'], 'reference' => $data['reference'] ?? null,
                'received_on' => $data['received_on'] ?? today(), 'notes' => $data['notes'] ?? null,
            ]);
            $payment->recorded_by = $by->id;
            $payment->save();

            Receipt::create(['number' => $this->numbers->next('RCT'), 'payment_id' => $payment->id, 'issued_at' => now()]);
            $this->recalculate($invoice);
            ActivityLog::record('payment.recorded', $payment, 'Payment of '.Money::format($amount, $invoice->currency)." on {$invoice->number}");

            return $payment;
        });
    }

    public function reversePayment(Payment $payment, User $by, string $reason): void
    {
        $this->authorize(Gate::forUser($by)->allows('reverse', $payment), 'You may not reverse this payment.');
        if ($payment->isReversed()) {
            throw ValidationException::withMessages(['payment' => 'This payment has already been reversed.']);
        }

        DB::transaction(function () use ($payment, $by, $reason) {
            $payment->forceFill(['reversed_at' => now(), 'reversed_by' => $by->id, 'reversal_reason' => $reason])->save();
            $this->recalculate($payment->invoice);
            ActivityLog::record('payment.reversed', $payment, 'Payment reversed: '.$reason);
        });
    }

    // ------------------------------------------------------------------ helpers

    /** Single place where totals and payment status are derived. */
    public function recalculate(Invoice $invoice): Invoice
    {
        $items = $invoice->items()->get();
        $subtotal = Money::add(...$items->pluck('line_subtotal')->all());
        $tax = Money::add(...$items->pluck('line_tax')->all());
        $total = Money::add($subtotal, $tax);
        $paid = Money::add(...$invoice->payments()->whereNull('reversed_at')->pluck('amount')->all());
        $credited = Money::add(...$invoice->creditNotes()->pluck('amount')->all());
        $balance = Money::max0(Money::sub(Money::sub($total, $paid), $credited));

        $status = $invoice->status;
        if (in_array($status, Invoice::ISSUED_STATUSES, true)) {
            $status = match (true) {
                Money::isZero($balance) => 'paid',
                Money::cmp($paid, 0) > 0 || Money::cmp($credited, 0) > 0 => 'partially_paid',
                default => 'issued',
            };
        }

        $invoice->forceFill([
            'subtotal' => $subtotal, 'tax_total' => $tax, 'total' => $total,
            'amount_paid' => $paid, 'amount_credited' => $credited,
            'balance' => $status === 'void' ? '0.00' : $balance, 'status' => $status,
        ])->save();

        return $invoice;
    }

    public function feeTaxRate(): string
    {
        return Settings::get('vat_registered') ? (string) Settings::get('vat_rate') : '0';
    }

    private function release(InvoiceItem $item): void
    {
        TimeEntry::where('invoice_item_id', $item->id)->get()->each(fn (TimeEntry $t) => $t->forceFill(['status' => 'unbilled', 'invoice_item_id' => null])->saveQuietly());
        Expense::where('invoice_item_id', $item->id)->get()->each(fn (Expense $e) => $e->forceFill(['status' => 'approved', 'invoice_item_id' => null])->saveQuietly());
    }

    private function authorize(bool $allowed, string $message): void
    {
        if (! $allowed) {
            throw new AuthorizationException($message);
        }
    }
}
