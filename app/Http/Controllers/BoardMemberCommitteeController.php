<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\User;
use App\Services\BoardMemberDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoardMemberCommitteeController extends Controller
{
    public function index(Request $request, BoardMemberDashboardService $dashboard): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isBoardMember(), 403);

        $terms = $user->board_member_id
            ? $dashboard->termsWithAssignmentsFor($user)
            : collect();
        $selectedTerm = $user->board_member_id
            ? $dashboard->resolveTermForUser($user, $request->integer('term') ?: null)
            : $dashboard->resolveTerm($request->integer('term') ?: null);
        $grouped = $user->board_member_id
            ? $dashboard->assignmentsGroupedByRole($user, $selectedTerm)
            : [
                'chair' => collect(),
                'vice_chair' => collect(),
                'member' => collect(),
            ];

        return view('board-member.committees.index', [
            'user' => $user,
            'unlinked' => $user->board_member_id === null,
            'terms' => $terms,
            'selectedTerm' => $selectedTerm,
            'roles' => $grouped,
            'totalAssignments' => collect($grouped)->flatten(1)->count(),
        ]);
    }

    public function show(Request $request, Committee $committee, BoardMemberDashboardService $dashboard): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isBoardMember(), 403);
        abort_unless($user->board_member_id !== null, 403);

        $terms = $dashboard->availableTerms();
        $selectedTerm = $dashboard->resolveTerm($request->integer('term') ?: null);
        $membership = $dashboard->membershipForCommittee($user, $committee, $selectedTerm);

        if ($membership === null) {
            $fallbackTerm = $dashboard->availableTerms()->first(
                fn ($term) => $dashboard->membershipForCommittee($user, $committee, $term) !== null
            );
            abort_unless($fallbackTerm !== null, 403);

            return redirect()->route('board-member.committees.show', [
                'committee' => $committee,
                'term' => $fallbackTerm->id,
            ]);
        }

        $memberTermIds = $dashboard->availableTerms()
            ->filter(fn ($term) => $dashboard->membershipForCommittee($user, $committee, $term) !== null)
            ->values();

        $roster = $dashboard->rosterForCommittee($committee, $selectedTerm);
        $agendas = $dashboard->agendaQueryForCommittee($user, $committee, $selectedTerm)
            ->orderByDesc('date_of_referral')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('board-member.committees.show', [
            'user' => $user,
            'committee' => $committee,
            'roleLabel' => $membership['role_label'],
            'terms' => $memberTermIds,
            'selectedTerm' => $selectedTerm,
            'roster' => $roster,
            'agendas' => $agendas,
            'stats' => $dashboard->agendaStatsForCommittee($user, $committee, $selectedTerm),
            'statuses' => config('agenda.statuses', []),
        ]);
    }
}
