<?php

namespace App\Policies;

use App\Models\SiteSetting;
use App\Models\User;

class SiteSettingPolicy
{
    public function viewAny(User $user): bool { return $user->can('settings.manage'); }
    public function view(User $user, SiteSetting $setting): bool { return $user->can('settings.manage'); }
    public function create(User $user): bool { return $user->can('settings.manage'); }
    public function update(User $user, SiteSetting $setting): bool { return $user->can('settings.manage'); }
    public function delete(User $user, SiteSetting $setting): bool { return $user->can('settings.manage'); }
}
