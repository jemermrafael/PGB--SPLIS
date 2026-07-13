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
        abort_unless($request->user()?->canAdmin(), 403);

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

        $mapCommitteeId = $request->filled('map_committee_id')
            ? (int) $request->integer('map_committee_id')
            : ($defaultCommittee?->id);
        $mapYear = (int) $request->integer('map_year', $focusYear);
        $mapMonth = $request->filled('map_month') ? max(1, min(12, (int) $request->integer('map_month'))) : null;
        $mapMeasure = $request->string('map_measure', 'both')->toString();
        if (! in_array($mapMeasure, ['both', 'agendas', 'resolutions'], true)) {
            $mapMeasure = 'both';
        }

        $mapCommittee = $committees->firstWhere('id', $mapCommitteeId) ?? $defaultCommittee;
        $committeeMap = $mapCommittee
            ? $executive->committeeMunicipalityMap($mapCommittee, $mapYear, $mapMonth, $mapMeasure)
            : [
                'municipalities' => [],
                'year' => $mapYear,
                'month' => $mapMonth,
                'committee' => '',
                'committee_id' => null,
                'measure' => $mapMeasure,
                'period_label' => '',
                'total' => 0,
            ];

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
            'mapMeasure' => $mapMeasure,
            'mapCommitteeId' => $mapCommittee?->id,
            'committeeMap' => $committeeMap,
        ]);
    }
}
