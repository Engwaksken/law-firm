<?php

namespace App\Policies;

use App\Models\User;

class MediaPolicy extends ContentPolicy
{
    /**
     * Unlike other content, media may be permanently erased (to remove the file itself).
     * MediaTable blocks it while the file is still referenced by pages or events.
     */
    public function forceDelete(User $user, mixed $model): bool
    {
        return $user->can('content.manage');
    }
}
