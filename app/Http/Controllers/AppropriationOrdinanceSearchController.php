<?php

namespace App\Http\Controllers;

use App\Models\AppropriationOrdinance;
use App\Services\AppropriationOrdinanceSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppropriationOrdinanceSearchController extends Controller
{
    public function __invoke(Request $request, AppropriationOrdinanceSearchService $search): JsonResponse
    {
        $this->authorize('viewAny', AppropriationOrdinance::class);

        $filters = $request->only(['q', 'series']);
        $filters['page'] = $request->integer('page', 1);

        $paginator = $search->paginate($filters, (int) config('appropriation_ordinances.per_page', 15));

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($item) => $search->toArray($item))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
