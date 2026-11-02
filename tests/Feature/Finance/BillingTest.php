<?php

namespace Tests\Feature\Finance;

use App\Events\InvoiceIssued;
use App\Finance\InvoiceService;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Matter;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Access;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Spec §23: "Billing calculations are tested." */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    private User $advocate;
    private User $finance;
    private User $finance2;
    private Matter $matter;
    private InvoiceService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->advocate = $this->userWithRole(Access::ADVOCATE);
        $this->finance = $this->userWithRole(Access::FINANCE_OFFICER);
        $this->finance2 = $this->userWithRole(Access::FINANCE_OFFICER);
        $this->matter = Matter::factory()->active()->ledBy($this->advocate)->withTeam([$this->advocate->id => 'lead'])->create();
        $this->billing = app(InvoiceService::class);
    }

    private function issued(?callable $setup = null): Invoice
    {
        TimeEntry::factory()->create(['matter_id' => $this->matter->id, 'user_id' => $this->advocate->id, 'minutes' => 120, 'rate' => '250000']);
        $invoice = $this->billing->draftFromUnbilled($this->matter, $this->advocate);
        $this->billing->submit($invoice, $this->advocate);

        return $this->billing->approve($invoice->refresh(), $this->finance);
    }

    public function test_money_helper_is_exact_and_rounds_half_up(): void
    {
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
        $this->assertSame('5000.04', Money::percent('27778', '18.0'));   // 5000.04 exactly
        $this->assertSame('2.01', Money::mul('0.335', '6'));             // 2.010 exactly
        $this->assertSame('0.01', Money::percent('0.05', '18'));        // 0.009 → 0.01
        $this->assertSame('375000.00', Money::forMinutes(90, '250000'));
        $this->assertSame('83333.33', Money::forMinutes(20, '250000'));  // 83333.333…
        $this->assertSame('-1.00', Money::sub('1', '2'));
        $this->assertSame('0.00', Money::max0('-5'));
    }

    public function test_time_entry_amount_is_derived_from_minutes_and_rate(): void
    {
        $entry = TimeEntry::factory()->create(['minutes' => 50, 'rate' => '300000']);
        $this->assertSame('250000.00', $entry->amount);

        $entry->update(['billable' => false]);
        $this->assertSame('0.00', $entry->fresh()->amount);
    }

    public function test_draft_from_unbilled_includes_billable_time_and_approved_expenses_only(): void
    {
        TimeEntry::factory()->create(['matter_id' => $this->matter->id, 'user_id' => $this->advocate->id, 'minutes' => 90, 'rate' => '300000']); // 450,000
        TimeEntry::factory()->create(['matter_id' => $this->matter->id, 'user_id' => $this->advocate->id, 'minutes' => 20, 'rate' => '250000']); // 83,333.33
        TimeEntry::factory()->nonBillable()->create(['matter_id' => $this->matter->id, 'user_id' => $this->advocate->id]);
        TimeEntry::factory()->create(['user_id' => $this->advocate->id]); // other matter
        Expense::factory()->approved()->create(['matter_id' => $this->matter->id, 'amount' => '150000']);
        Expense::factory()->create(['matter_id' => $this->matter->id, 'amount' => '999999']); // pending: excluded

        $invoice = $this->billing->draftFromUnbilled($this->matter, $this->advocate);

        $this->assertSame('draft', $invoice->status);
        $this->assertCount(3, $invoice->items);
        $this->assertSame('683333.33', $invoice->subtotal);
        $this->assertSame('0.00', $invoice->tax_total, 'Not VAT registered by default');
        $this->assertSame('683333.33', $invoice->total);
        $this->assertSame('683333.33', $invoice->balance);
        $this->assertSame(2, TimeEntry::where('status', 'billed')->count());
        $this->assertSame(1, Expense::where('status', 'billed')->count());
    }

    public function test_vat_applies_to_fees_but_not_disbursements(): void
    {
        Settings::setMany(['vat_registered' => true, 'vat_rate' => '18']);
        TimeEntry::factory()->create(['matter_id' => $this->matter->id, 'user_id' => $this->advocate->id, 'minutes' => 60, 'rate' => '100000']);
        Expense::factory()->approved()->create(['matter_id' => $this->matter->id, 'amount' => '50000']);

        $invoice = $this->billing->draftFromUnbilled($this->matter, $this->advocate);

        $this->assertSame('150000.00', $invoice->subtotal);
        $this->assertSame('18000.00', $invoice->tax_total);
        $this->assertSame('168000.00', $invoice->total);
    }

    public function test_removing_a_line_or_deleting_the_draft_releases_work(): void
    {
        TimeEntry::factory()->count(2)->create(['matter_id' => $this->matter->id, 'user_id' => $this->advocate->id]);
        $invoice = $this->billing->draftFromUnbilled($this->matter, $this->advocate);

        $this->billing->removeItem($invoice->items->first(), $this->advocate);
        $this->assertSame(1, TimeEntry::where('status', 'unbilled')->count());
        $this->assertCount(1, $invoice->fresh()->items);

        $this->billing->deleteDraft($invoice->fresh(), $this->advocate);
        $this->assertSame(2, TimeEntry::where('status', 'unbilled')->count());
        $this->assertSame(0, Invoice::count());
    }

    public function test_issue_requires_a_different_approver_and_allocates_sequential_numbers(): void
    {
        Event::fake([InvoiceIssued::class]);
        TimeEntry::factory()->create(['matter_id' => $this->matter->id, 'user_id' => $this->advocate->id]);

        // Finance prepares and submits; the same person may not approve.
        $invoice = $this->billing->draftFromUnbilled($this->matter, $this->finance);
        $this->billing->submit($invoice, $this->finance);
        try {
            $this->billing->approve($invoice->refresh(), $this->finance);
            $this->fail('Preparer must not approve their own invoice');
        } catch (AuthorizationException) {
        }
        $this->assertFalse($this->advocate->can('approve', $invoice), 'Advocates cannot issue invoices');

        $this->billing->approve($invoice->refresh(), $this->finance2);
        $this->assertSame('INV-'.now()->year.'-00001', $invoice->number);
        $this->assertSame('issued', $invoice->status);
        $this->assertSame(today()->addDays(30)->toDateString(), $invoice->due_date->toDateString());
        Event::assertDispatched(InvoiceIssued::class);

        $second = $this->issued();
        $this->assertSame('INV-'.now()->year.'-00002', $second->number);
    }

    public function test_issued_invoices_and_billed_time_are_locked(): void
    {
        $invoice = $this->issued();
        $item = $invoice->items->first();

        $this->assertFalse($this->finance->can('update', $invoice));
        $item->unit_price = '1';
        $this->assertFalse($item->save(), 'Lines of issued invoices cannot change');
        $this->assertFalse($invoice->delete(), 'Issued invoices cannot be deleted');

        $entry = TimeEntry::where('status', 'billed')->first();
        $entry->minutes = 1;
        $this->assertFalse($entry->save(), 'Billed time cannot change');

        $this->expectException(AuthorizationException::class);
        $this->billing->addItem($invoice, $this->finance, ['description' => 'Extra', 'unit_price' => '100']);
    }

    public function test_partial_and_full_payments_update_balance_and_status_with_receipts(): void
    {
        $invoice = $this->issued(); // 2h × 250,000 = 500,000

        $first = $this->billing->recordPayment($invoice, $this->finance, ['amount' => '200000', 'method' => 'mobile_money', 'reference' => 'MM123']);
        $invoice->refresh();
        $this->assertSame('partially_paid', $invoice->status);
        $this->assertSame('300000.00', $invoice->balance);
        $this->assertSame('RCT-'.now()->year.'-00001', $first->receipt->number);

        $this->billing->recordPayment($invoice, $this->finance, ['amount' => '300000', 'method' => 'bank_transfer']);
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('0.00', $invoice->balance);
        $this->assertSame('500000.00', $invoice->amount_paid);
    }

    public function test_overpayment_wrong_currency_and_zero_amounts_are_refused(): void
    {
        $invoice = $this->issued();

        foreach ([
            ['amount' => '500000.01', 'method' => 'cash'],
            ['amount' => '0', 'method' => 'cash'],
            ['amount' => '1000', 'method' => 'cash', 'currency' => 'USD'],
            ['amount' => '1000', 'method' => 'bitcoin'],
        ] as $data) {
            try {
                $this->billing->recordPayment($invoice->refresh(), $this->finance, $data);
                $this->fail('Should be refused: '.json_encode($data));
            } catch (ValidationException) {
            }
        }
        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_payments_cannot_be_recorded_on_drafts_or_by_non_finance_staff(): void
    {
        TimeEntry::factory()->create(['matter_id' => $this->matter->id, 'user_id' => $this->advocate->id]);
        $draft = $this->billing->draftFromUnbilled($this->matter, $this->advocate);

        $this->assertFalse($this->finance->can('recordPayment', $draft));
        $this->assertFalse($this->advocate->can('recordPayment', $this->issued()));
    }

    public function test_reversing_a_payment_restores_the_balance_and_payments_are_never_deleted(): void
    {
        $invoice = $this->issued();
        $payment = $this->billing->recordPayment($invoice, $this->finance, ['amount' => '500000', 'method' => 'cheque']);
        $this->assertSame('paid', $invoice->fresh()->status);

        $this->billing->reversePayment($payment, $this->finance, 'Cheque bounced');
        $invoice->refresh();
        $this->assertSame('issued', $invoice->status);
        $this->assertSame('500000.00', $invoice->balance);

        $this->assertFalse($payment->fresh()->delete());
        $this->assertDatabaseHas('activity_logs', ['event' => 'payment.reversed']);
    }

    public function test_credit_notes_reduce_balance_but_never_below_zero(): void
    {
        $invoice = $this->issued();

        $note = $this->billing->creditNote($invoice, $this->finance, '100000', 'Agreed discount');
        $this->assertSame('CRN-'.now()->year.'-00001', $note->number);
        $invoice->refresh();
        $this->assertSame('400000.00', $invoice->balance);
        $this->assertSame('partially_paid', $invoice->status);

        $this->expectException(ValidationException::class);
        $this->billing->creditNote($invoice, $this->finance, '400000.01', 'Too much');
    }

    public function test_void_releases_work_but_is_refused_once_paid(): void
    {
        $invoice = $this->issued();
        $this->billing->void($invoice, $this->finance, 'Issued to wrong client');

        $this->assertSame('void', $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->balance);
        $this->assertSame(1, TimeEntry::where('status', 'unbilled')->count(), 'Time can be re-billed');

        $paid = $this->issued();
        $this->billing->recordPayment($paid, $this->finance, ['amount' => '1000', 'method' => 'cash']);
        $this->expectException(\Throwable::class);
        $this->billing->void($paid->refresh(), $this->finance, 'x');
    }

    public function test_rejected_invoice_returns_to_draft_with_reason(): void
    {
        TimeEntry::factory()->create(['matter_id' => $this->matter->id, 'user_id' => $this->advocate->id]);
        $invoice = $this->billing->draftFromUnbilled($this->matter, $this->advocate);
        $this->billing->submit($invoice, $this->advocate);

        $this->billing->reject($invoice->refresh(), $this->finance, 'Rate looks wrong');

        $this->assertSame('draft', $invoice->status);
        $this->assertStringContainsString('Rate looks wrong', $invoice->internal_notes);
    }

    public function test_outsiders_cannot_invoice_a_matter(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->billing->draftFromUnbilled($this->matter, $this->userWithRole(Access::ADVOCATE));
    }

    public function test_numbers_must_be_allocated_in_a_transaction(): void
    {
        $this->expectException(\LogicException::class);
        // Outside a transaction (RefreshDatabase wraps tests in one, so roll it back first).
        \DB::rollBack();
        try {
            app(\App\Finance\DocumentNumbers::class)->next('INV');
        } finally {
            \DB::beginTransaction();
        }
    }
}
