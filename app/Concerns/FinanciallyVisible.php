<?php

namespace App\Concerns;

use App\Models\User;
use App\Support\MatterAccess;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Financial records (time, expenses, invoices, payments) are visible to:
 *  - finance staff (invoices.issue / payments.record / reports.export): all records, because billing and
 *    audit need the complete ledger (documented decision: line descriptions should therefore stay
 *    neutral on restricted matters);
 *  - otherwise, people who can see the matter.
 */
trait FinanciallyVisible
{
    public static function userSeesAllFinance(User $user): bool
    {
        return $user->is_active && ($user->can('invoices.issue') || $user->can('payments.record') || $user->can('reports.export'));
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (static::userSeesAllFinance($user)) {
            return;
        }
        $column = $query->getModel()->getTable().'.matter_id';
        $query->where(function (Builder $q) use ($user, $column) {
            MatterAccess::scopeOwned($q, $user, $column);
            if (in_array('user_id', $q->getModel()->getFillable(), true)) {
                $q->orWhere($q->getModel()->getTable().'.user_id', $user->id);
            }
        });
    }

    public function canBeSeenBy(User $user): bool
    {
        return static::userSeesAllFinance($user)
            || (isset($this->user_id) && $this->user_id === $user->id)
            || ($this->matter !== null && MatterAccess::canView($user, $this->matter));
    }
}
