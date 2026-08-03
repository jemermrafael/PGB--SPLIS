<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgendaItem;
use App\Services\AgendaItemRepository;
use App\Support\AgendaFieldOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArchivesController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->canAdmin() ?? false, 403);

        return view('admin.archives.index', [
            'statuses' => config('agenda.statuses', []),
            'committees' => AgendaFieldOptions::committees(),
            'archivedCount' => AgendaItem::query()->archived()->count(),
        ]);
    }

    public function search(Request $request, AgendaItemRepository $repository): JsonResponse
    {
        abort_unless($request->user()?->canAdmin() ?? false, 403);

        $filters = $request->only([
            'number',
            'title',
            'sender',
            'committee',
            'status',
        ]);

        $paginator = $repository->paginateArchived($filters, 25);

        return response()->json([
            'data' => collect($paginator->items())->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'archived_count' => AgendaItem::query()->archived()->count(),
        ]);
    }
}
