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
        $user = $request->user();
        abort_unless(
            $user?->canAdmin()
                || ($user?->isBoardMember() && $user->board_member_id),
            403,
        );

        $scope = $executive->resolveScope($user);

        return $executive->usingScope($scope, function () use ($request, $executive, $scope): JsonResponse {
            $committee = null;

            if ($request->filled('committee_id')) {
                $committee = Committee::query()
                    ->active()
                    ->find((int) $request->integer('committee_id'));

                if ($committee === null) {
                    return response()->json([
                        'message' => 'Committee not found.',
                    ], 422);
                }

                if (! $scope->allowsCommittee($committee->id)) {
                    return response()->json([
                        'message' => 'Committee is outside your assignments.',
                    ], 403);
                }
            } elseif (! $scope->isFull()) {
                $committee = null;
            }

            $year = (int) $request->integer('year', (int) now()->format('Y'));
            $month = $request->filled('month') ? max(1, min(12, (int) $request->integer('month'))) : null;

            return response()->json(
                $executive->committeeMunicipalityMap($committee, $year, $month)
            );
        });
    }
}
