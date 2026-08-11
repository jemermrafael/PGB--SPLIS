<?php

namespace App\Policies;

use App\Models\LegislativeSession;
use App\Models\User;
use App\Support\UserCapability;

class LegislativeSessionPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isMunicipalViewer()) {
            return false;
        }

        if ($user->canEncode() || $user->canAdmin()) {
            return $user->hasModuleCapability(UserCapability::ORDER_OF_BUSINESS);
        }

        return true;
    }

    public function view(User $user, LegislativeSession $session): bool
    {
        if ($user->isMunicipalViewer()) {
            return false;
        }

        if ($user->isBoardMember()) {
            return $session->isVisibleToBoardMembers();
        }

        if ($user->canEncode() || $user->canAdmin()) {
            return $user->hasModuleCapability(UserCapability::ORDER_OF_BUSINESS);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasModuleCapability(UserCapability::ORDER_OF_BUSINESS);
    }

    public function update(User $user, LegislativeSession $session): bool
    {
        return $user->hasModuleCapability(UserCapability::ORDER_OF_BUSINESS);
    }

    public function delete(User $user, LegislativeSession $session): bool
    {
        return $user->hasModuleCapability(UserCapability::ORDER_OF_BUSINESS);
    }
}
