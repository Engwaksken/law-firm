<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/** Payments are recorded through invoices and reversed, never edited or deleted. */
class PaymentPolicy
{
    public function viewAny(User $user): bool { return Payment::userSeesAllFinance($user); }
    public function view(User $user, Payment $payment): bool { return $payment->canBeSeenBy($user); }
    public function create(User $user): bool { return false; }
    public function update(User $user, Payment $payment): bool { return false; }
    public function delete(User $user, Payment $payment): bool { return false; }

    public function reverse(User $user, Payment $payment): bool
    {
        return ! $payment->isReversed() && $user->can('payments.record') && $payment->recorded_by !== null;
    }
}
