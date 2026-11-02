<?php

namespace App\Concerns;

use App\Models\Matter;
use App\Models\User;
use App\Support\MatterAccess;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** For records that live inside a matter: access follows the matter. */
trait BelongsToMatter
{
    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class)->withTrashed();
    }

    /** Records whose matter $user may see. Records without a matter are excluded unless $includeUnassigned. */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        MatterAccess::scopeOwned($query, $user, $query->getModel()->getTable().'.matter_id');
    }

    public function canBeSeenBy(User $user): bool
    {
        return $this->matter !== null && MatterAccess::canView($user, $this->matter);
    }
}
