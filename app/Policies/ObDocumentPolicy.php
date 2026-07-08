<?php

namespace App\Policies;

use App\Models\ObDocument;
use App\Models\User;

class ObDocumentPolicy
{
    public function view(User $user, ObDocument $document): bool
    {
        if ($user->isBoardMember()) {
            return $document->isFinal();
        }

        return true;
    }

    public function update(User $user, ObDocument $document): bool
    {
        return $user->canEncode();
    }
}
