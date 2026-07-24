<?php

namespace App\Policies;

use App\Models\Committee;
use App\Models\User;

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
        return $user->canEncode();
    }

    public function update(User $user, Committee $committee): bool
    {
        return $user->canEncode();
    }

    public function delete(User $user, Committee $committee): bool
    {
        return $user->canEncode();
    }

    public function manageIcon(User $user, Committee $committee): bool
    {
        return $user->isSuperadmin();
    }
}
