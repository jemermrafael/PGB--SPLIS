<?php

namespace App\Policies;

use App\Models\BoardMemberCommitteeReport;
use App\Models\User;
use App\Support\UserCapability;

class BoardMemberCommitteeReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isLinkedBoardMember($user)
            || $user->hasModuleCapability(UserCapability::COMMITTEE_REPORTS);
    }

    public function view(User $user, BoardMemberCommitteeReport $report): bool
    {
        return $this->ownsAsBoardMember($user, $report)
            || $user->hasModuleCapability(UserCapability::COMMITTEE_REPORTS);
    }

    public function create(User $user): bool
    {
        return $this->isLinkedBoardMember($user)
            || $user->hasModuleCapability(UserCapability::COMMITTEE_REPORTS);
    }

    public function update(User $user, BoardMemberCommitteeReport $report): bool
    {
        return $this->canMutate($user, $report);
    }

    public function delete(User $user, BoardMemberCommitteeReport $report): bool
    {
        return $this->canMutate($user, $report);
    }

    protected function isLinkedBoardMember(User $user): bool
    {
        return $user->isBoardMember() && $user->board_member_id !== null;
    }

    protected function ownsAsBoardMember(User $user, BoardMemberCommitteeReport $report): bool
    {
        return $this->isLinkedBoardMember($user)
            && (int) $report->board_member_id === (int) $user->board_member_id;
    }

    /**
     * Board members may mutate their own reports.
     * Staff may mutate only reports they submitted (not BM-submitted reports).
     */
    protected function canMutate(User $user, BoardMemberCommitteeReport $report): bool
    {
        if ($this->ownsAsBoardMember($user, $report)) {
            return true;
        }

        return $user->hasModuleCapability(UserCapability::COMMITTEE_REPORTS)
            && (int) $report->submitted_by === (int) $user->id;
    }
}
