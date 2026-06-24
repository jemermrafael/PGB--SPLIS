<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\SeriesYear;
use App\Services\ResolutionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSearchController extends Controller
{
    public function __invoke(Request $request, ResolutionRepository $repository): JsonResponse
    {
        $filters = $request->only([
            'number',
            'title',
            'author',
            'committee',
            'keyword',
            'date_from',
            'date_to',
            'series',
            'category_id',
            'department_id',
            'municipality_id',
            'status',
            'document_type',
            'has_pdf',
        ]);

        $filters['page'] = $request->integer('page', 1);

        $paginator = $repository->paginateDocuments($filters, 12);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($item) => $repository->documentToArray($item))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
