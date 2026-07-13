<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\AppropriationOrdinance;
use App\Models\Committee;
use App\Models\CommitteeTerm;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\User;
use App\Services\AgendaItemRepository;
use App\Services\BoardMemberDashboardService;
use App\Services\CommitteeMonitoringService;
use App\Support\SqlDateExpression;
use Illuminate\Support\Facades\DB;

class DashboardAnalyticsService
{
    public function __construct(
        protected CommitteeMonitoringService $committeeMonitoring,
        protected BoardMemberDashboardService $boardMemberDashboard,
        protected AgendaItemRepository $agendaItems,
    ) {}

    /**
     * @return array<string, int>
     */
    public function agendaPipelineStats(): array
    {
        $agendaStats = $this->agendaItems->stats();

        return [
            'pending' => $agendaStats['pending'],
            'expiring_soon' => $agendaStats['expiring_soon'],
            'published' => AgendaItem::query()
                ->where(function ($query): void {
                    $query->whereNotNull('published_at')
                        ->orWhereNotNull('resolution_id')
                        ->orWhereNotNull('ordinance_id')
                        ->orWhereNotNull('appropriation_ordinance_id');
                })
                ->count(),
            'on_final_ob' => AgendaItem::query()->whereHas('finalObPlacements')->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function agendaPipelineStatsForYears(int $yearFrom, int $yearTo): array
    {
        $startDate = $yearFrom.'-01-01';
        $endDate = $yearTo.'-12-31';

        $pending = AgendaItem::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', AgendaItem::STATUS_PENDING)
            ->count();

        $expiringSoon = AgendaItem::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', AgendaItem::STATUS_PENDING)
            ->whereNotNull('date_received')
            ->whereNotNull('prescribed_days')
            ->where('prescribed_days', '>', 0)
            ->get(['date_received', 'prescribed_days'])
            ->filter(function (AgendaItem $agendaItem): bool {
                $deadline = $agendaItem->date_received?->copy()->addDays((int) $agendaItem->prescribed_days);
                if (! $deadline) {
                    return false;
                }

                return $deadline->isFuture() && $deadline->lte(now()->copy()->addDays($this->expiringSoonDays()));
            })
            ->count();

        $published = AgendaItem::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where(function ($query): void {
                $query->whereNotNull('published_at')
                    ->orWhereNotNull('resolution_id')
                    ->orWhereNotNull('ordinance_id')
                    ->orWhereNotNull('appropriation_ordinance_id');
            })
            ->count();

        $onFinalOb = AgendaItem::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereHas('finalObPlacements')
            ->count();

        return [
            'pending' => $pending,
            'expiring_soon' => $expiringSoon,
            'published' => $published,
            'on_final_ob' => $onFinalOb,
        ];
    }

    /**
     * @return list<array{year: int, resolutions: int, ordinances: int, total: int}>
     */
    public function outputByYear(int $span = 8): array
    {
        $currentYear = (int) date('Y');
        $years = range($currentYear - $span + 1, $currentYear);

        $resolutionCounts = Resolution::query()
            ->selectRaw('series, count(*) as aggregate')
            ->whereIn('series', $years)
            ->groupBy('series')
            ->pluck('aggregate', 'series');

        $ordinanceCounts = Ordinance::query()
            ->selectRaw('series_year, count(*) as aggregate')
            ->whereIn('series_year', $years)
            ->groupBy('series_year')
            ->pluck('aggregate', 'series_year');

        $appropriationCounts = AppropriationOrdinance::query()
            ->selectRaw('series_year, count(*) as aggregate')
            ->whereIn('series_year', $years)
            ->groupBy('series_year')
            ->pluck('aggregate', 'series_year');

        return collect($years)
            ->map(function (int $year) use ($resolutionCounts, $ordinanceCounts, $appropriationCounts): array {
                $resolutions = (int) ($resolutionCounts[$year] ?? 0);
                $ordinances = (int) ($ordinanceCounts[$year] ?? 0) + (int) ($appropriationCounts[$year] ?? 0);

                return [
                    'year' => $year,
                    'resolutions' => $resolutions,
                    'ordinances' => $ordinances,
                    'total' => $resolutions + $ordinances,
                ];
            })
            ->all();
    }

    /**
     * @return list<array{year: int, resolutions: int, ordinances: int, total: int}>
     */
    public function outputByYearRange(int $yearFrom, int $yearTo): array
    {
        if ($yearFrom > $yearTo) {
            [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
        }

        $years = range($yearFrom, $yearTo);

        $resolutionCounts = Resolution::query()
            ->selectRaw('series, count(*) as aggregate')
            ->whereBetween('series', [$yearFrom, $yearTo])
            ->groupBy('series')
            ->pluck('aggregate', 'series');

        $ordinanceCounts = Ordinance::query()
            ->selectRaw('series_year, count(*) as aggregate')
            ->whereBetween('series_year', [$yearFrom, $yearTo])
            ->groupBy('series_year')
            ->pluck('aggregate', 'series_year');

        $appropriationCounts = AppropriationOrdinance::query()
            ->selectRaw('series_year, count(*) as aggregate')
            ->whereBetween('series_year', [$yearFrom, $yearTo])
            ->groupBy('series_year')
            ->pluck('aggregate', 'series_year');

        return collect($years)
            ->map(function (int $year) use ($resolutionCounts, $ordinanceCounts, $appropriationCounts): array {
                $resolutions = (int) ($resolutionCounts[$year] ?? 0);
                $ordinances = (int) ($ordinanceCounts[$year] ?? 0) + (int) ($appropriationCounts[$year] ?? 0);

                return [
                    'year' => $year,
                    'resolutions' => $resolutions,
                    'ordinances' => $ordinances,
                    'total' => $resolutions + $ordinances,
                ];
            })
            ->all();
    }

    /**
     * @return list<array{month: string, resolutions: int, ordinances: int, total: int}>
     */
    public function outputByMonth(int $year): array
    {
        $monthSelect = SqlDateExpression::month('date_approved');

        $resolutionCounts = Resolution::query()
            ->selectRaw($monthSelect.' as month_no, count(*) as aggregate')
            ->where('series', $year)
            ->whereNotNull('date_approved')
            ->whereYear('date_approved', $year)
            ->groupBy('month_no')
            ->pluck('aggregate', 'month_no');

        $ordinanceCounts = Ordinance::query()
            ->selectRaw($monthSelect.' as month_no, count(*) as aggregate')
            ->where('series_year', $year)
            ->whereNotNull('date_approved')
            ->whereYear('date_approved', $year)
            ->groupBy('month_no')
            ->pluck('aggregate', 'month_no');

        $appropriationCounts = AppropriationOrdinance::query()
            ->selectRaw($monthSelect.' as month_no, count(*) as aggregate')
            ->where('series_year', $year)
            ->whereNotNull('date_approved')
            ->whereYear('date_approved', $year)
            ->groupBy('month_no')
            ->pluck('aggregate', 'month_no');

        return collect(range(1, 12))
            ->map(function (int $month) use ($resolutionCounts, $ordinanceCounts, $appropriationCounts): array {
                $resolutions = (int) ($resolutionCounts[$month] ?? 0);
                $ordinances = (int) ($ordinanceCounts[$month] ?? 0) + (int) ($appropriationCounts[$month] ?? 0);

                return [
                    'month' => now()->setMonth($month)->startOfMonth()->format('M'),
                    'resolutions' => $resolutions,
                    'ordinances' => $ordinances,
                    'total' => $resolutions + $ordinances,
                ];
            })
            ->all();
    }

    /**
     * @return list<array{committee_id: int, committee: string, total: int, pending: int, url: string}>
     */
    public function committeeWorkload(int $limit = 8): array
    {
        $termId = CommitteeTerm::query()->current()->value('id');

        $committees = Committee::query()
            ->active()
            ->ordered()
            ->when($termId, fn ($query) => $query->withRosterForTerm($termId))
            ->get(['id', 'name']);

        $rows = $committees
            ->map(function (Committee $committee): array {
                $stats = $this->committeeMonitoring->stats([
                    'committee_id' => $committee->id,
                ]);

                return [
                    'committee_id' => $committee->id,
                    'committee' => $committee->name,
                    'total' => $stats['total'],
                    'pending' => $stats['pending'],
                    'url' => route('committee-monitoring.index', [
                        'committee_id' => $committee->id,
                        'view' => 'pending',
                    ]),
                ];
            })
            ->filter(fn (array $row): bool => $row['total'] > 0)
            ->sortByDesc('pending')
            ->values()
            ->take($limit)
            ->all();

        return $rows;
    }

    /**
     * @return list<array{committee_id: int, committee: string, total: int, pending: int, url: string}>
     */
    public function committeeWorkloadFiltered(?int $committeeId = null, int $limit = 8): array
    {
        return collect($this->committeeWorkload($limit * 3))
            ->when($committeeId, fn ($rows) => $rows->where('committee_id', $committeeId))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     done_this_month: int,
     *     done_last_month: int,
     *     published: int,
     *     on_final_ob: int,
     *     committee_breakdown: list<array{committee_id: int, committee: string, pending: int, done: int, url: string}>
     * }
     */
    public function boardMemberTrends(User $user): array
    {
        $base = $this->boardMemberDashboard->committeeAgendaQueryFor($user);
        $now = now();
        $lastMonth = $now->copy()->subMonth();

        $doneThisMonth = (clone $base)
            ->where('status', AgendaItem::STATUS_DONE)
            ->where(function ($query) use ($now): void {
                $query->where(function ($inner) use ($now): void {
                    $inner->whereMonth('published_at', $now->month)
                        ->whereYear('published_at', $now->year);
                })->orWhere(function ($inner) use ($now): void {
                    $inner->whereMonth('date_passed', $now->month)
                        ->whereYear('date_passed', $now->year);
                });
            })
            ->count();

        $doneLastMonth = (clone $base)
            ->where('status', AgendaItem::STATUS_DONE)
            ->where(function ($query) use ($lastMonth): void {
                $query->where(function ($inner) use ($lastMonth): void {
                    $inner->whereMonth('published_at', $lastMonth->month)
                        ->whereYear('published_at', $lastMonth->year);
                })->orWhere(function ($inner) use ($lastMonth): void {
                    $inner->whereMonth('date_passed', $lastMonth->month)
                        ->whereYear('date_passed', $lastMonth->year);
                });
            })
            ->count();

        $committeeBreakdown = $this->boardMemberCommitteeBreakdown($user);

        return [
            'done_this_month' => $doneThisMonth,
            'done_last_month' => $doneLastMonth,
            'published' => (clone $base)->whereNotNull('published_at')->count(),
            'on_final_ob' => (clone $base)->whereHas('finalObPlacements')->count(),
            'committee_breakdown' => $committeeBreakdown,
        ];
    }

    /**
     * @return list<array{committee_id: int, committee: string, pending: int, done: int, url: string}>
     */
    public function boardMemberCommitteeBreakdown(User $user): array
    {
        return $this->boardMemberDashboard->committeesFor($user)
            ->map(function (Committee $committee) use ($user): array {
                $stats = $this->boardMemberDashboard->agendaStatsForCommittee($user, $committee);

                return [
                    'committee_id' => $committee->id,
                    'committee' => $committee->name,
                    'pending' => $stats['pending'],
                    'done' => $stats['done'],
                    'url' => route('board-member.agenda.committee', $committee),
                ];
            })
            ->filter(fn (array $row): bool => ($row['pending'] + $row['done']) > 0)
            ->sortByDesc('pending')
            ->values()
            ->all();
    }

    /**
     * @param  list<array{year?: int, committee?: string, resolutions?: int, ordinances?: int, total?: int, pending?: int, done?: int}>  $rows
     * @return list<array<string, mixed>>
     */
    public function normalizeBarChartRows(array $rows, string $labelKey, string|array $valueKeys): array
    {
        if ($rows === []) {
            return [];
        }

        $valueKeys = (array) $valueKeys;
        $computed = collect($rows)
            ->map(fn (array $row): int => (int) collect($valueKeys)->sum(fn (string $key) => (int) ($row[$key] ?? 0)))
            ->all();

        $max = max(1, ...$computed);

        return collect($rows)
            ->map(function (array $row) use ($labelKey, $valueKeys, $max): array {
                $value = (int) collect($valueKeys)->sum(fn (string $key) => (int) ($row[$key] ?? 0));

                return array_merge($row, [
                    'label' => (string) ($row[$labelKey] ?? ''),
                    'value' => $value,
                    'percent' => (int) round(($value / $max) * 100),
                ]);
            })
            ->all();
    }

    public function expiringSoonDays(): int
    {
        return $this->boardMemberDashboard->expiringSoonDays();
    }

    /**
     * @return array{referred: int, pending: int, scheduled: int, reports: int, completed: int}
     */
    public function committeeOverviewStats(?int $committeeId, int $yearFrom, int $yearTo): array
    {
        $stats = $this->committeeMonitoring->stats([
            'committee_id' => $committeeId,
            'date_from' => $yearFrom.'-01-01',
            'date_to' => $yearTo.'-12-31',
        ]);

        return [
            'referred' => $stats['total'],
            'pending' => $stats['pending'],
            'scheduled' => $stats['with_schedule'],
            'reports' => $stats['with_report'],
            'completed' => $stats['completed'],
        ];
    }

    /**
     * @return list<array{label: string, value: int, color: string}>
     */
    public function agendaStatusDistribution(int $yearFrom, int $yearTo): array
    {
        $startDate = $yearFrom.'-01-01';
        $endDate = $yearTo.'-12-31';

        $counts = AgendaItem::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $labels = config('agenda.statuses', [
            AgendaItem::STATUS_PENDING => 'Pending',
            AgendaItem::STATUS_DONE => 'Accomplished',
            AgendaItem::STATUS_LAPSED => 'Lapsed',
            AgendaItem::STATUS_NO_DUE_DATE => 'No due date',
        ]);

        $colors = [
            AgendaItem::STATUS_PENDING => '#f59e0b',
            AgendaItem::STATUS_DONE => '#10b981',
            AgendaItem::STATUS_LAPSED => '#f97316',
            AgendaItem::STATUS_NO_DUE_DATE => '#64748b',
        ];

        return collect($labels)
            ->map(fn (string $label, string $status): array => [
                'label' => $label,
                'value' => (int) ($counts[$status] ?? 0),
                'color' => $colors[$status] ?? '#38bdf8',
            ])
            ->filter(fn (array $row): bool => $row['value'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return list<array{committee: string, pending: int, completed: int, total: int, url: string}>
     */
    public function committeeRanking(int $limit = 10): array
    {
        $termId = CommitteeTerm::query()->current()->value('id');

        return Committee::query()
            ->active()
            ->ordered()
            ->when($termId, fn ($query) => $query->withRosterForTerm($termId))
            ->get(['id', 'name'])
            ->map(function (Committee $committee): array {
                $stats = $this->committeeMonitoring->stats([
                    'committee_id' => $committee->id,
                ]);

                return [
                    'committee' => $committee->name,
                    'pending' => $stats['pending'],
                    'completed' => $stats['completed'],
                    'total' => $stats['total'],
                    'url' => route('committee-monitoring.index', [
                        'committee_id' => $committee->id,
                    ]),
                ];
            })
            ->filter(fn (array $row): bool => $row['total'] > 0)
            ->sortByDesc('total')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  list<array{year: int, resolutions: int, ordinances: int, total: int}>  $outputByYear
     * @param  list<array{month: string, resolutions: int, ordinances: int, total: int}>  $outputByMonth
     * @param  list<array{label: string, value: int, color: string}>  $statusDistribution
     * @param  list<array{committee: string, pending: int, completed: int, total: int}>  $committeeRanking
     * @return array<string, mixed>
     */
    public function chartPayload(
        array $outputByYear,
        array $outputByMonth,
        array $statusDistribution,
        array $committeeRanking,
        array $committeeOverview,
        array $agendaPipeline,
    ): array {
        return [
            'outputByYear' => [
                'labels' => collect($outputByYear)->pluck('year')->map(fn ($y) => (string) $y)->all(),
                'resolutions' => collect($outputByYear)->pluck('resolutions')->all(),
                'ordinances' => collect($outputByYear)->pluck('ordinances')->all(),
                'totals' => collect($outputByYear)->pluck('total')->all(),
            ],
            'outputByMonth' => [
                'labels' => collect($outputByMonth)->pluck('month')->all(),
                'resolutions' => collect($outputByMonth)->pluck('resolutions')->all(),
                'ordinances' => collect($outputByMonth)->pluck('ordinances')->all(),
                'totals' => collect($outputByMonth)->pluck('total')->all(),
            ],
            'statusDistribution' => [
                'labels' => collect($statusDistribution)->pluck('label')->all(),
                'values' => collect($statusDistribution)->pluck('value')->all(),
                'colors' => collect($statusDistribution)->pluck('color')->all(),
            ],
            'committeeRanking' => [
                'labels' => collect($committeeRanking)->pluck('committee')->map(fn ($name) => \Illuminate\Support\Str::limit($name, 28))->all(),
                'pending' => collect($committeeRanking)->pluck('pending')->all(),
                'completed' => collect($committeeRanking)->pluck('completed')->all(),
                'totals' => collect($committeeRanking)->pluck('total')->all(),
            ],
            'pipelineTrend' => [
                'labels' => ['Pending', 'Expiring', 'Published', 'On final OB'],
                'values' => [
                    $agendaPipeline['pending'],
                    $agendaPipeline['expiring_soon'],
                    $agendaPipeline['published'],
                    $agendaPipeline['on_final_ob'],
                ],
            ],
            'committeeOverview' => $committeeOverview,
        ];
    }
}
