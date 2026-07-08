<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\User;
use App\Services\BoardMemberDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoardMemberCommitteeAgendaController extends Controller
{
    public function show(Request $request, Committee $committee, BoardMemberDashboardService $dashboard): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isBoardMember(), 403);

        $membership = $dashboard->membershipForCommittee($user, $committee);
        abort_unless($membership !== null, 403);

        return view('board-member.agenda.committee', [
            'user' => $user,
            'committee' => $committee,
            'roleLabel' => $membership['role_label'],
            'statuses' => config('agenda.statuses', []),
            'stats' => $dashboard->agendaStatsForCommittee($user, $committee),
        ]);
    }
}
