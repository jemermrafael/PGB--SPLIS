<?php

namespace App\Http\Controllers;

use App\Services\IncomingDocumentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncomingSearchController extends Controller
{
    public function __invoke(Request $request, IncomingDocumentRepository $repository): JsonResponse
    {
        $filters = $request->only([
            'number',
            'title',
            'committee',
            'keyword',
            'date_from',
            'date_to',
            'series',
            'status',
            'link_status',
            'source',
            'category_id',
            'department_id',
            'municipality_id',
            'has_pdf',
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
        ]);
    }
}
