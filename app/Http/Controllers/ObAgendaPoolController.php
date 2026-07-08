<?php

namespace App\Http\Controllers;

use App\Models\LegislativeSession;
use App\Services\ObAgendaPoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ObAgendaPoolController extends Controller
{
    public function __invoke(
        Request $request,
        LegislativeSession $legislativeSession,
        ObAgendaPoolService $pool,
    ): JsonResponse {
        abort_unless($legislativeSession->obDocument, 404);

        $this->authorize('update', $legislativeSession->obDocument);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $paginator = $pool->paginate(
            $validated['q'] ?? null,
            $validated['page'] ?? 1,
            excludeDocumentId: $legislativeSession->obDocument->id,
        );

        return response()->json([
            'data' => $pool->serializeItems($paginator),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
