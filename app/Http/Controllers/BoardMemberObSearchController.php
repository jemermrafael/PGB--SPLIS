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

        $query = $dashboard->orderOfBusinessQuery();

        if ($request->filled('q')) {
            $term = trim($request->string('q'));
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('session_number', 'like', '%'.$term.'%')
                    ->orWhere('venue', 'like', '%'.$term.'%')
                    ->orWhere('session_kind', 'like', '%'.$term.'%');
            });
        }

        $perPage = min(max($request->integer('per_page', 10), 5), 50);

        $paginator = $query->paginate($perPage)->through(function (LegislativeSession $session) use ($user) {
            $document = $session->obDocument;
            $canView = $document !== null && $user->can('view', $document);

            return [
                'id' => $session->id,
                'title' => $session->displayTitle(),
                'session_date' => $session->session_date?->format('Y-m-d'),
                'session_date_label' => $session->session_date?->format('F j, Y'),
                'venue' => $session->venue,
                'kind_label' => $session->sessionKindLabel(),
                'can_view' => $canView,
                'print_url' => $canView ? route('ob.document.print', $session) : null,
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
