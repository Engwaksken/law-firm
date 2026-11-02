<?php

namespace App\Policies;

use App\Models\CareerApplication;
use App\Models\User;

/** Applications hold personal data (CVs): restricted to careers.manage; never created by staff. */
class CareerApplicationPolicy
{
    public function viewAny(User $user): bool { return $user->can('careers.manage'); }
    public function view(User $user, CareerApplication $application): bool { return $user->can('careers.manage'); }
    public function create(User $user): bool { return false; }
    public function update(User $user, CareerApplication $application): bool { return $user->can('careers.manage'); }
    public function delete(User $user, CareerApplication $application): bool { return $user->can('careers.manage'); }
    public function restore(User $user, CareerApplication $application): bool { return $user->can('careers.manage'); }
    /** Permanent erasure (e.g. on a data-subject request) also deletes the CV file. */
    public function forceDelete(User $user, CareerApplication $application): bool { return $user->can('careers.manage'); }
    public function downloadCv(User $user, CareerApplication $application): bool { return $user->can('careers.manage'); }
}
