<?php

namespace App\Policies;

use App\Models\Committee;
use App\Models\User;
use App\Support\UserCapability;

class CommitteePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Committee $committee): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasModuleCapability(UserCapability::COMMITTEES);
    }

    public function update(User $user, Committee $committee): bool
    {
        return $user->hasModuleCapability(UserCapability::COMMITTEES);
    }

    public function delete(User $user, Committee $committee): bool
    {
        return $user->hasModuleCapability(UserCapability::COMMITTEES);
    }

    public function manageIcon(User $user, Committee $committee): bool
    {
        return $user->canManageIconLibrary();
    }
}
