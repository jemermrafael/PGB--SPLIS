<?php

namespace App\Policies;

use App\Models\BoardMember;
use App\Models\User;
use App\Support\UserCapability;

class BoardMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BoardMember $boardMember): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasModuleCapability(UserCapability::COMMITTEES);
    }

    public function update(User $user, BoardMember $boardMember): bool
    {
        return $user->hasModuleCapability(UserCapability::COMMITTEES);
    }

    public function delete(User $user, BoardMember $boardMember): bool
    {
        return $user->hasModuleCapability(UserCapability::COMMITTEES);
    }
}
