<?php

namespace App\Support;

use App\Models\Matter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * THE rule for who can see a matter, used by policies AND by every query that
 * lists matters or matter-owned records (notes, documents, hearings, tasks…).
 *
 *  - Assigned users (or the lead) always see their matters.
 *  - Users with `matters.view-all` see every matter EXCEPT restricted ones
 *    (ethical walls) they aren't assigned to.
 *  - Nobody else, including the System Owner, sees a matter they aren't assigned to.
 *    The System Owner can still see unrestricted matters through view-all.
 */
class MatterAccess
{
    public static function canView(User $user, Matter $matter): bool
    {
        if (! $user->is_active) {
            return false;
        }
        if (static::isAssigned($user, $matter)) {
            return true;
        }

        return ! $matter->is_restricted && static::hasViewAll($user);
    }

    public static function isAssigned(User $user, Matter $matter): bool
    {
        return $matter->lead_user_id === $user->id
            || $matter->assignments()->where('user_id', $user->id)->exists();
    }

    /** Lead, or assigned as a lawyer (lead/associate). */
    public static function isLawyerOn(User $user, Matter $matter): bool
    {
        return $matter->lead_user_id === $user->id
            || $matter->assignments()->where('user_id', $user->id)->whereIn('role', ['lead', 'associate'])->exists();
    }

    /** Restrict a Matter query to what $user may see. */
    public static function scope(Builder $query, User $user): Builder
    {
        if (! $user->is_active) {
            return $query->whereRaw('1 = 0');
        }

        $assigned = fn (Builder $q) => $q->where('matters.lead_user_id', $user->id)
            ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('matter_assignments')
                ->whereColumn('matter_assignments.matter_id', 'matters.id')
                ->where('matter_assignments.user_id', $user->id));

        if (static::hasViewAll($user)) {
            return $query->where(fn (Builder $q) => $q->where('matters.is_restricted', false)->orWhere($assigned));
        }

        if (! $user->can('matters.view-assigned')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($assigned);
    }

    /** Restrict a query on a matter-owned model (has matter_id) to visible matters. */
    public static function scopeOwned(Builder $query, User $user, string $column = 'matter_id'): Builder
    {
        return $query->whereIn($column, static::scope(Matter::query()->withTrashed(), $user)->select('matters.id'));
    }

    private static function hasViewAll(User $user): bool
    {
        return $user->checkPermissionTo('matters.view-all') || $user->isUnrestricted();
    }
}
