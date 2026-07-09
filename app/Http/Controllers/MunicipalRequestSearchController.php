<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AgendaItemRepository;
use App\Services\MunicipalRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MunicipalRequestSearchController extends Controller
{
    public function __invoke(
        Request $request,
        MunicipalRequestService $service,
        AgendaItemRepository $repository,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isMunicipalViewer(), 403);
        abort_unless($user->municipality_id !== null, 403);

        $baseQuery = $service->requestQueryFor($user);

        $filters = $request->only([
            'number',
            'title',
            'status',
            'date_from',
            'date_to',
            'due_soon',
            'expiring_soon',
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
            $baseQuery->orderByDesc('date_received')->orderByDesc('id'),
            $filters,
            $perPage,
        );

        $data = collect($paginator->items())->map(function (array $item) {
            $item['url'] = route('municipal.requests.show', $item['id']);

            return $item;
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'stats' => $service->statsFor($user),
        ]);
    }
}
