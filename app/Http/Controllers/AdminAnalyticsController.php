<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Services\ExecutiveAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAnalyticsController extends Controller
{
    public function __invoke(Request $request, ExecutiveAnalyticsService $executive): View
    {
        abort_unless($request->user()?->canAdmin() || $request->user()?->isBoardMember(), 403);

        $currentYear = (int) now()->format('Y');
        $minYear = $executive->earliestDataYear();
        $yearFrom = (int) $request->integer('year_from', $currentYear - 4);
        $yearTo = (int) $request->integer('year_to', $currentYear);
        $focusYear = (int) $request->integer('focus_year', $yearTo);

        if ($yearFrom > $yearTo) {
            [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
        }

        $focusYear = max($yearFrom, min($yearTo, $focusYear));

        $committees = $executive->mapCommitteeOptions();
        $defaultCommittee = $committees->first(
            fn (Committee $committee): bool => stripos($committee->name, 'Housing') !== false
        ) ?? $committees->first();

        $mapCommittee = $defaultCommittee;
        if ($request->exists('map_committee_id')) {
            $mapCommittee = $request->filled('map_committee_id')
                ? ($committees->firstWhere('id', (int) $request->integer('map_committee_id')) ?? $defaultCommittee)
                : null;
        }

        $mapYear = (int) $request->integer('map_year', $focusYear);
        $mapMonth = $request->filled('map_month') ? max(1, min(12, (int) $request->integer('map_month'))) : null;

        $committeeMap = $executive->committeeMunicipalityMap($mapCommittee, $mapYear, $mapMonth);

        $payload = $executive->payload($yearFrom, $yearTo, $focusYear);

        return view('admin.analytics.index', [
            'yearFrom' => $yearFrom,
            'yearTo' => $yearTo,
            'focusYear' => $focusYear,
            'minYear' => $minYear,
            'kpis' => $payload['kpis'],
            'chartPayload' => $payload,
            'committees' => $committees,
            'mapYear' => $mapYear,
            'mapMonth' => $mapMonth,
            'mapCommitteeId' => $mapCommittee?->id,
            'committeeMap' => $committeeMap,
        ]);
    }
}
