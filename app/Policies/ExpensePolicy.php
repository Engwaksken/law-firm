<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\Matter;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return Expense::userSeesAllFinance($user) || $user->can('matters.view-assigned') || $user->can('matters.view-all');
    }

    public function view(User $user, Expense $expense): bool { return $expense->canBeSeenBy($user); }

    public function create(User $user): bool { return $user->can('matters.view-assigned') || $user->can('matters.view-all') || $user->can('invoices.issue'); }

    public function createFor(User $user, Matter $matter): bool
    {
        return ((new MatterPolicy)->contribute($user, $matter) || $user->can('invoices.issue')) && $matter->status !== 'archived';
    }

    public function update(User $user, Expense $expense): bool
    {
        return in_array($expense->status, ['pending', 'rejected'], true) && ($expense->user_id === $user->id || $user->can('expenses.approve'));
    }

    public function delete(User $user, Expense $expense): bool { return $this->update($user, $expense); }

    /** Approvers may not approve their own expense claims. */
    public function approve(User $user, Expense $expense): bool
    {
        return $expense->status === 'pending' && $user->can('expenses.approve') && $expense->user_id !== $user->id;
    }
}
