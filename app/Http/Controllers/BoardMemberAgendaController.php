<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BoardMemberDashboardService;
use App\Support\AgendaFieldOptions;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoardMemberAgendaController extends Controller
{
    public function index(Request $request, BoardMemberDashboardService $dashboard): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isBoardMember(), 403);

        return view('board-member.agenda.index', [
            'user' => $user,
            'unlinked' => $user->board_member_id === null,
            'statuses' => config('agenda.statuses', []),
            'senders' => AgendaFieldOptions::senders(),
            'committees' => $user->board_member_id
                ? $dashboard->committeesFor($user)->pluck('name')->values()->all()
                : [],
            'outcomes' => AgendaFieldOptions::outcomes(),
            'stats' => $user->board_member_id
                ? $dashboard->agendaStatsFor($user)
                : ['pending' => 0, 'due_soon' => 0, 'done' => 0, 'lapsed' => 0],
        ]);
    }
}
