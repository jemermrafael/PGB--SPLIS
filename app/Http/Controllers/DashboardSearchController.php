<?php

namespace App\Http\Controllers;

use App\Services\DashboardDocumentSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSearchController extends Controller
{
    public function __invoke(Request $request, DashboardDocumentSearchService $search): JsonResponse
    {
        $user = $request->user();

        // Executive dashboard document search is staff-only (encoders/admins).
        abort_unless($user && ($user->canEncode() || $user->canAdmin()), 403);

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

        $paginator = $search->paginate($filters, 12);

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
