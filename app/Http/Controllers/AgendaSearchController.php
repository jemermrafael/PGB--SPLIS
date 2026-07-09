<?php

namespace App\Http\Controllers;

use App\Services\AgendaItemRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgendaSearchController extends Controller
{
    public function __invoke(Request $request, AgendaItemRepository $repository): JsonResponse
    {
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
            'has_remarks',
        ]);

        $filters['page'] = $request->integer('page', 1);

        $paginator = $repository->paginate($filters, 25);

        return response()->json([
            'data' => collect($paginator->items())->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'stats' => $repository->stats(),
        ]);
    }
}
