<?php

namespace App\Http\Controllers;

use App\Models\DirectoryEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectorySearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DirectoryEntry::class);

        $term = trim($request->string('q')->toString());

        $entries = DirectoryEntry::query()
            ->search($term)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(50, ['*'], 'page', $request->integer('page', 1));

        $emptyMessage = $term !== ''
            ? 'No directory entries match your search.'
            : 'No directory entries yet.';

        return response()->json([
            'html' => view('directory.partials.entries-tbody', [
                'entries' => $entries,
                'emptyMessage' => $emptyMessage,
            ])->render(),
            'meta' => [
                'total' => $entries->total(),
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
            ],
        ]);
    }
}
