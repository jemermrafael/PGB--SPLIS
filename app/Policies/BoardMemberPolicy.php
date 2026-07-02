<?php

namespace App\Policies;

use App\Models\BoardMember;
use App\Models\User;

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
        return $user->canEncode();
    }

    public function update(User $user, BoardMember $boardMember): bool
    {
        return $user->canEncode();
    }

    public function delete(User $user, BoardMember $boardMember): bool
    {
        return $user->canEncode();
    }
}
