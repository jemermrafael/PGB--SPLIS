<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Services\DashboardAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAnalyticsController extends Controller
{
    public function __invoke(Request $request, DashboardAnalyticsService $analytics): View
    {
        abort_unless($request->user()?->canAdmin(), 403);

        $currentYear = (int) now()->format('Y');
        $yearFrom = (int) $request->integer('year_from', $currentYear - 4);
        $yearTo = (int) $request->integer('year_to', $currentYear);
        $focusYear = (int) $request->integer('focus_year', $yearTo);
        $committeeId = $request->integer('committee_id') ?: null;
        $chartLimit = min(20, max(5, (int) $request->integer('chart_limit', 10)));

        if ($yearFrom > $yearTo) {
            [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
        }

        $focusYear = max($yearFrom, min($yearTo, $focusYear));

        $agendaPipeline = $analytics->agendaPipelineStatsForYears($yearFrom, $yearTo);
        $outputByYear = $analytics->outputByYearRange($yearFrom, $yearTo);
        $outputByMonth = $analytics->outputByMonth($focusYear);
        $committeeOverview = $analytics->committeeOverviewStats($committeeId, $yearFrom, $yearTo);
        $statusDistribution = $analytics->agendaStatusDistribution($yearFrom, $yearTo);
        $committeeRanking = $analytics->committeeRanking($chartLimit);

        $chartPayload = $analytics->chartPayload(
            $outputByYear,
            $outputByMonth,
            $statusDistribution,
            $committeeRanking,
            $committeeOverview,
            $agendaPipeline,
        );

        $monitoringBaseUrl = route('committee-monitoring.index', array_filter([
            'committee_id' => $committeeId,
            'date_from' => $yearFrom.'-01-01',
            'date_to' => $yearTo.'-12-31',
        ]));

        return view('admin.analytics.index', [
            'yearFrom' => $yearFrom,
            'yearTo' => $yearTo,
            'focusYear' => $focusYear,
            'committeeId' => $committeeId,
            'chartLimit' => $chartLimit,
            'committees' => Committee::query()->active()->ordered()->get(['id', 'name']),
            'agendaPipeline' => $agendaPipeline,
            'committeeOverview' => $committeeOverview,
            'expiringSoonDays' => $analytics->expiringSoonDays(),
            'chartPayload' => $chartPayload,
            'monitoringUrls' => [
                'referred' => $monitoringBaseUrl,
                'pending' => $monitoringBaseUrl.(str_contains($monitoringBaseUrl, '?') ? '&' : '?').'view=pending',
                'scheduled' => $monitoringBaseUrl.(str_contains($monitoringBaseUrl, '?') ? '&' : '?').'view=scheduled',
                'reports' => $monitoringBaseUrl.(str_contains($monitoringBaseUrl, '?') ? '&' : '?').'view=reports',
                'completed' => $monitoringBaseUrl.(str_contains($monitoringBaseUrl, '?') ? '&' : '?').'view=completed',
            ],
        ]);
    }
}
