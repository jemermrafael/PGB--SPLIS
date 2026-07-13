<?php

namespace App\Policies;

use App\Models\LegislativeSession;
use App\Models\User;

class LegislativeSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isMunicipalViewer();
    }

    public function view(User $user, LegislativeSession $session): bool
    {
        if ($user->isMunicipalViewer()) {
            return false;
        }

        if ($user->isBoardMember()) {
            return $session->isVisibleToBoardMembers();
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->canEncode();
    }

    public function update(User $user, LegislativeSession $session): bool
    {
        return $user->canEncode();
    }

    public function delete(User $user, LegislativeSession $session): bool
    {
        return $user->canEncode();
    }
}
