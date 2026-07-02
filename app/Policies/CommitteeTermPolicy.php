<?php

namespace App\Policies;

use App\Models\CommitteeTerm;
use App\Models\User;

class CommitteeTermPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CommitteeTerm $committeeTerm): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canEncode();
    }

    public function update(User $user, CommitteeTerm $committeeTerm): bool
    {
        return $user->canEncode();
    }

    public function delete(User $user, CommitteeTerm $committeeTerm): bool
    {
        return $user->canEncode();
    }
}
