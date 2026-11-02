<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\Matter;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return Invoice::userSeesAllFinance($user) || $user->can('matters.view-assigned') || $user->can('matters.view-all');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $invoice->canBeSeenBy($user);
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.issue') || $user->can('matters.view-assigned');
    }

    /** Finance staff, or a lawyer on the matter (lead/associate), may prepare a draft. */
    public function createFor(User $user, Matter $matter): bool
    {
        return $user->can('invoices.issue') || (new MatterPolicy)->update($user, $matter);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->isEditable()
            && ($invoice->created_by === $user->id || $user->can('invoices.issue') || ($invoice->matter && (new MatterPolicy)->update($user, $invoice->matter)));
    }

    /** Separation of duties: the approver is neither the preparer nor the submitter. */
    public function approve(User $user, Invoice $invoice): bool
    {
        return $invoice->status === 'pending_approval'
            && $user->can('invoices.issue')
            && $invoice->created_by !== $user->id
            && $invoice->submitted_by !== $user->id;
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $invoice->status === 'issued' && $user->can('invoices.issue');
    }

    public function credit(User $user, Invoice $invoice): bool
    {
        return in_array($invoice->status, ['issued', 'partially_paid'], true) && $user->can('invoices.issue');
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return in_array($invoice->status, ['issued', 'partially_paid'], true) && $user->can('payments.record');
    }

    public function downloadPdf(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $invoice->status === 'draft' && $this->update($user, $invoice);
    }
}
