<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\User;
use App\Services\AgendaItemRepository;
use App\Services\BoardMemberDashboardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardMemberAgendaSearchController extends Controller
{
    public function __invoke(
        Request $request,
        BoardMemberDashboardService $dashboard,
        AgendaItemRepository $repository,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isBoardMember(), 403);
        abort_unless($user->board_member_id !== null, 403);

        $committeeId = $request->integer('committee_id') ?: null;
        $committee = null;

        if ($committeeId) {
            $committee = Committee::query()->findOrFail($committeeId);
            abort_unless($dashboard->membershipForCommittee($user, $committee) !== null, 403);
            $baseQuery = $dashboard->agendaQueryForCommittee($user, $committee);
        } else {
            $baseQuery = $dashboard->committeeAgendaQueryFor($user);
        }

        $filters = $request->only([
            'number',
            'title',
            'sender',
            'committee',
            'status',
            'outcome',
            'date_from',
            'date_to',
            'series',
            'due_soon',
            'has_incoming',
        ]);

        if ($request->filled('q')) {
            $term = trim($request->string('q'));
            $baseQuery->where(function (Builder $query) use ($term): void {
                $query->where('title', 'like', '%'.$term.'%')
                    ->orWhere('tracking_no', 'like', '%'.$term.'%')
                    ->orWhere('reso_ord_ao_no', 'like', '%'.$term.'%');
            });
            unset($filters['title'], $filters['number']);
        }

        $perPage = min(max($request->integer('per_page', 15), 5), 50);

        $paginator = $repository->paginateFromBuilder(
            $baseQuery->orderByDesc('date_of_referral')->orderByDesc('date_received')->orderByDesc('id'),
            $filters,
            $perPage,
        );

        $stats = $committee
            ? $dashboard->agendaStatsForCommittee($user, $committee)
            : $dashboard->agendaStatsFor($user);

        return response()->json([
            'data' => collect($paginator->items())->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'stats' => $stats,
        ]);
    }
}
