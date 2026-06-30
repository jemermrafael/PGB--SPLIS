<?php

namespace App\Policies;

use App\Models\AgendaItem;
use App\Models\User;

class AgendaItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AgendaItem $agendaItem): bool
    {
        return true;
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
}
