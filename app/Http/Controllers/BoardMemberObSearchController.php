<?php

namespace App\Http\Controllers;

use App\Models\LegislativeSession;
use App\Models\User;
use App\Services\BoardMemberDashboardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardMemberObSearchController extends Controller
{
    public function __invoke(Request $request, BoardMemberDashboardService $dashboard): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isBoardMember(), 403);

        $query = $dashboard->orderOfBusinessQuery()
            ->with(['obDocument.blocks.agendaItem']);

        if ($request->filled('q')) {
            $term = trim($request->string('q'));
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('session_number', 'like', '%'.$term.'%')
                    ->orWhere('venue', 'like', '%'.$term.'%')
                    ->orWhere('session_kind', 'like', '%'.$term.'%');
            });
        }

        $perPage = min(max($request->integer('per_page', 10), 5), 50);

        $paginator = $query->paginate($perPage)->through(function (LegislativeSession $session) use ($user, $dashboard) {
            $document = $session->obDocument;
            $canView = $document !== null && $user->can('view', $document);
            $myItems = $user->board_member_id
                ? $dashboard->myCommitteeItemsOnSession($user, $session)
                : collect();

            return [
                'id' => $session->id,
                'title' => $session->displayTitle(),
                'session_date' => $session->session_date?->format('Y-m-d'),
                'session_date_label' => $session->session_date?->format('F j, Y'),
                'venue' => $session->venue,
                'kind_label' => $session->sessionKindLabel(),
                'can_view' => $canView,
                'print_url' => $canView ? route('ob.document.print', $session) : null,
                'ics_url' => route('board-member.sessions.ics', $session),
                'my_items_count' => $myItems->count(),
                'my_items' => $myItems->take(5)->map(fn ($item) => [
                    'id' => $item->id,
                    'label' => $item->displayLabel(),
                    'title' => \Illuminate\Support\Str::limit($item->title ?: 'Untitled', 80),
                    'url' => route('agenda.show', $item),
                ])->values(),
            ];
        });

        return response()->json([
            'data' => collect($paginator->items())->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
