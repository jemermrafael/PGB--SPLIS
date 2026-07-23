<?php

namespace App\Policies;

use App\Models\BoardMemberCommitteeReport;
use App\Models\User;

class BoardMemberCommitteeReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBoardMember() && $user->board_member_id !== null;
    }

    public function view(User $user, BoardMemberCommitteeReport $report): bool
    {
        return $this->ownsReport($user, $report);
    }

    public function create(User $user): bool
    {
        return $user->isBoardMember() && $user->board_member_id !== null;
    }

    public function update(User $user, BoardMemberCommitteeReport $report): bool
    {
        return $this->ownsReport($user, $report);
    }

    public function delete(User $user, BoardMemberCommitteeReport $report): bool
    {
        return $this->ownsReport($user, $report);
    }

    protected function ownsReport(User $user, BoardMemberCommitteeReport $report): bool
    {
        return $user->isBoardMember()
            && $user->board_member_id !== null
            && (int) $report->board_member_id === (int) $user->board_member_id;
    }
}
