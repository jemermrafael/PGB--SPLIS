<?php

namespace App\Policies;

use App\Models\AgendaItem;
use App\Models\User;
use App\Support\MunicipalRequestAccess;

class AgendaItemPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isMunicipalViewer();
    }

    public function view(User $user, AgendaItem $agendaItem): bool
    {
        return MunicipalRequestAccess::userCanViewAgenda($user, $agendaItem);
    }

    public function create(User $user): bool
    {
        return $user->canEncode();
    }

    public function update(User $user, AgendaItem $agendaItem): bool
    {
        return $user->canEncode();
    }

    public function delete(User $user, AgendaItem $agendaItem): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $user->canEncode() && ! $agendaItem->hasIncoming();
    }

    public function promote(User $user, AgendaItem $agendaItem): bool
    {
        return $user->canEncode() && ! $agendaItem->hasIncoming();
    }

    public function unlinkIncoming(User $user, AgendaItem $agendaItem): bool
    {
        return $user->canEncode() && $agendaItem->hasIncoming();
    }

    public function unlinkResolution(User $user, AgendaItem $agendaItem): bool
    {
        return $user->canEncode() && $agendaItem->resolution_id !== null;
    }

    public function addToOrderOfBusiness(User $user, AgendaItem $agendaItem): bool
    {
        return $user->canEncode();
    }
}
