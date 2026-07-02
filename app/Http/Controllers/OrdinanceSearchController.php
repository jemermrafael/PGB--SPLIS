<?php

namespace App\Http\Controllers;

use App\Services\OrdinanceSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrdinanceSearchController extends Controller
{
    public function __invoke(Request $request, OrdinanceSearchService $search): JsonResponse
    {
        $filters = $request->only([
            'number',
            'title',
            'series',
            'classification',
            'date_from',
            'date_to',
            'has_pdf',
            'publication_status',
        ]);

        $filters['page'] = $request->integer('page', 1);

        $paginator = $search->paginate($filters, config('ordinances.per_page', 15));

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
