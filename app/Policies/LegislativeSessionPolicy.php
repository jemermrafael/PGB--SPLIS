<?php

namespace App\Policies;

use App\Models\LegislativeSession;
use App\Models\User;

class LegislativeSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LegislativeSession $session): bool
    {
        if ($user->isBoardMember()) {
            return $session->obDocument?->isFinal() ?? false;
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
