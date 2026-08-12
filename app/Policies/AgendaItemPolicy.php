<?php

namespace App\Policies;

use App\Models\AgendaItem;
use App\Models\User;
use App\Support\MunicipalRequestAccess;
use App\Support\UserCapability;

class AgendaItemPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isMunicipalViewer()) {
            return false;
        }

        // Regular board members use /my-agenda (scoped). Staff agenda list/search is encoder/admin/VG.
        if ($user->isBoardMember() && ! $user->isViceGovernorBoardMember()) {
            return false;
        }

        if ($user->canEncode() || $user->canAdmin()) {
            return $user->hasModuleCapability(UserCapability::AGENDA)
                || $user->hasModuleCapability(UserCapability::ORDER_OF_BUSINESS);
        }

        return true;
    }

    public function view(User $user, AgendaItem $agendaItem): bool
    {
        return MunicipalRequestAccess::userCanViewAgenda($user, $agendaItem);
    }

    public function create(User $user): bool
    {
        return $user->hasModuleCapability(UserCapability::AGENDA);
    }

    public function update(User $user, AgendaItem $agendaItem): bool
    {
        return $user->hasModuleCapability(UserCapability::AGENDA) && ! $agendaItem->isArchived();
    }

    public function delete(User $user, AgendaItem $agendaItem): bool
    {
        if ($agendaItem->isArchived()) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        return $user->hasModuleCapability(UserCapability::AGENDA) && ! $agendaItem->hasIncoming();
    }

    public function archive(User $user, AgendaItem $agendaItem): bool
    {
        return $user->canAdmin() && ! $agendaItem->isArchived() && ! $agendaItem->trashed();
    }

    public function restoreArchive(User $user, AgendaItem $agendaItem): bool
    {
        return $user->canAdmin() && $agendaItem->isArchived() && ! $agendaItem->trashed();
    }

    public function promote(User $user, AgendaItem $agendaItem): bool
    {
        return $user->hasModuleCapability(UserCapability::AGENDA) && ! $agendaItem->isArchived() && ! $agendaItem->hasIncoming();
    }

    public function unlinkIncoming(User $user, AgendaItem $agendaItem): bool
    {
        return $user->hasModuleCapability(UserCapability::AGENDA) && ! $agendaItem->isArchived() && $agendaItem->hasIncoming();
    }

    public function unlinkResolution(User $user, AgendaItem $agendaItem): bool
    {
        return $user->hasModuleCapability(UserCapability::AGENDA) && ! $agendaItem->isArchived() && $agendaItem->resolution_id !== null;
    }

    public function addToOrderOfBusiness(User $user, AgendaItem $agendaItem): bool
    {
        return $user->hasModuleCapability(UserCapability::ORDER_OF_BUSINESS)
            && ! $agendaItem->isArchived()
            && $agendaItem->status !== AgendaItem::STATUS_DONE
            && ! $agendaItem->obPlacements()->exists();
    }

    public function removeFromOrderOfBusiness(User $user, AgendaItem $agendaItem): bool
    {
        return $user->hasModuleCapability(UserCapability::ORDER_OF_BUSINESS)
            && ! $agendaItem->isArchived()
            && $agendaItem->obPlacements()->exists();
    }

    public function linkOutput(User $user, AgendaItem $agendaItem): bool
    {
        return $user->hasModuleCapability(UserCapability::AGENDA) && ! $agendaItem->isArchived() && $agendaItem->needsOutputLink();
    }
}
