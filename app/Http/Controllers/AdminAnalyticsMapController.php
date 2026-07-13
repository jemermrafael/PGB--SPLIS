<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Services\ExecutiveAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAnalyticsMapController extends Controller
{
    public function __invoke(Request $request, ExecutiveAnalyticsService $executive): JsonResponse
    {
        abort_unless($request->user()?->canAdmin(), 403);

        $committee = Committee::query()
            ->active()
            ->find((int) $request->integer('committee_id'));

        if ($committee === null) {
            return response()->json([
                'message' => 'Committee not found.',
            ], 422);
        }

        $year = (int) $request->integer('year', (int) now()->format('Y'));
        $month = $request->filled('month') ? max(1, min(12, (int) $request->integer('month'))) : null;
        $measure = $request->string('measure', 'both')->toString();

        return response()->json(
            $executive->committeeMunicipalityMap($committee, $year, $month, $measure)
        );
    }
}
