<?php

namespace App\Http\Controllers;

use App\Services\ResolutionRepository;
use App\Support\DocumentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResolutionSearchController extends Controller
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
            'has_pdf',
        ]);

        $filters['document_type'] = DocumentType::RESOLUTION;
        $filters['page'] = $request->integer('page', 1);

        $paginator = $repository->paginateDocuments($filters, config('resolutions.per_page'));

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
