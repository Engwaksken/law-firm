<?php

namespace App\Finance;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function decide(Expense $expense, User $by, bool $approve, ?string $reason = null): void
    {
        if (! Gate::forUser($by)->allows('approve', $expense)) {
            throw new AuthorizationException('You may not approve this expense.');
        }
        if (! $approve && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Give a reason for rejecting the expense.']);
        }

        $expense->forceFill([
            'status' => $approve ? 'approved' : 'rejected',
            'approved_by' => $by->id,
            'approved_at' => now(),
            'rejection_reason' => $approve ? null : $reason,
        ])->save();

        ActivityLog::record($approve ? 'expense.approved' : 'expense.rejected', $expense, 'Expense '.($approve ? 'approved' : 'rejected'));
    }

    public function writeOff(TimeEntry $entry, User $by, string $reason): void
    {
        if (! Gate::forUser($by)->allows('writeOff', $entry)) {
            throw new AuthorizationException('You may not write off this time.');
        }

        $entry->forceFill(['status' => 'written_off'])->saveQuietly();
        ActivityLog::record('time.written-off', $entry, 'Time written off: '.$reason);
    }
}
