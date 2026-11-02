<?php

namespace App\Policies;

use App\Models\ContactInquiry;
use App\Models\User;

class ContactInquiryPolicy
{
    public function viewAny(User $user): bool { return $user->can('inquiries.manage'); }
    public function view(User $user, ContactInquiry $inquiry): bool { return $user->can('inquiries.manage'); }
    public function create(User $user): bool { return false; }
    public function update(User $user, ContactInquiry $inquiry): bool { return $user->can('inquiries.manage'); }
    public function delete(User $user, ContactInquiry $inquiry): bool { return $user->can('inquiries.manage'); }
}
