<?php

namespace App\Policies;

use App\Models\CommitteeTerm;
use App\Models\User;
use App\Support\UserCapability;

class CommitteeTermPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isGuest() && ! $user->isMunicipalViewer();
    }

    public function view(User $user, CommitteeTerm $committeeTerm): bool
    {
        return ! $user->isGuest() && ! $user->isMunicipalViewer();
    }

    public function create(User $user): bool
    {
        return $user->hasModuleCapability(UserCapability::COMMITTEES);
    }

    public function update(User $user, CommitteeTerm $committeeTerm): bool
    {
        return $user->hasModuleCapability(UserCapability::COMMITTEES);
    }

    public function delete(User $user, CommitteeTerm $committeeTerm): bool
    {
        return $user->hasModuleCapability(UserCapability::COMMITTEES);
    }
}
