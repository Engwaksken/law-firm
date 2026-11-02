<?php

namespace App\Policies;

use App\Models\Engagement;
use App\Models\User;
use App\Support\MatterAccess;
use Illuminate\Database\Eloquent\Model;

class EngagementPolicy extends MatterChildPolicy
{
    /** Drafting terms: lawyers on the matter or matter managers. Approved engagements are locked. */
    public function update(User $user, Model $record): bool
    {
        return in_array($record->status, ['draft', 'declined'], true)
            && $record->matter !== null && (new MatterPolicy)->update($user, $record->matter);
    }

    /** Approval needs engagements.approve, and the approver must not be the person who submitted it. */
    public function approve(User $user, Engagement $engagement): bool
    {
        return $engagement->status === 'pending_approval'
            && $user->can('engagements.approve')
            && $engagement->matter !== null && MatterAccess::canView($user, $engagement->matter)
            && $engagement->submitted_by !== $user->id;
    }

    public function delete(User $user, Model $record): bool
    {
        return $record->status === 'draft' && parent::delete($user, $record);
    }
}
