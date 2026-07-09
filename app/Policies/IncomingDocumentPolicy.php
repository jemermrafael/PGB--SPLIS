<?php

namespace App\Policies;

use App\Models\IncomingDocument;
use App\Models\User;

class IncomingDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isMunicipalViewer();
    }

    public function view(User $user, IncomingDocument $incomingDocument): bool
    {
        return ! $user->isMunicipalViewer();
    }

    public function create(User $user): bool
    {
        return $user->canEncode();
    }

    public function update(User $user, IncomingDocument $incomingDocument): bool
    {
        return $user->canEncode() && ! $incomingDocument->isLinked();
    }

    public function link(User $user, IncomingDocument $incomingDocument): bool
    {
        return $user->canEncode() && ! $incomingDocument->isLinked();
    }

    public function publish(User $user, IncomingDocument $incomingDocument): bool
    {
        return $user->canEncode() && ! $incomingDocument->isLinked();
    }
}
