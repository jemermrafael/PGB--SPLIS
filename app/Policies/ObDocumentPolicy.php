<?php

namespace App\Policies;

use App\Models\ObDocument;
use App\Models\User;
use App\Support\UserCapability;

class ObDocumentPolicy
{
    public function view(User $user, ObDocument $document): bool
    {
        if ($user->isMunicipalViewer()) {
            return false;
        }

        if ($user->isBoardMember()) {
            return $document->legislativeSession?->isVisibleToBoardMembers() ?? false;
        }

        if ($user->canEncode() || $user->canAdmin()) {
            return $user->hasModuleCapability(UserCapability::ORDER_OF_BUSINESS);
        }

        return true;
    }

    public function update(User $user, ObDocument $document): bool
    {
        return $user->hasModuleCapability(UserCapability::ORDER_OF_BUSINESS);
    }
}
