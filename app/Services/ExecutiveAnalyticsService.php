<?php

namespace App\Services;

use App\Enums\OrdinanceBoardMemberRole;
use App\Enums\OrdinancePublicationStatus;
use App\Models\ActivityLog;
use App\Models\AgendaItem;
use App\Models\AppropriationOrdinance;
use App\Models\Category;
use App\Models\Committee;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Support\BataanPoliticalMap;
use App\Support\CommitteeLookup;
use App\Support\ExecutiveAnalyticsScope;
use App\Support\SqlDateExpression;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExecutiveAnalyticsService
{
    protected ExecutiveAnalyticsScope $scope;

    public function __construct(
        protected DashboardAnalyticsService $dashboard,
        protected BoardMemberDashboardService $boardMembers,
    ) {
        $this->scope = ExecutiveAnalyticsScope::full();
    }

    public function resolveScope(?\App\Models\User $user): ExecutiveAnalyticsScope
    {
        if ($user === null) {
            return ExecutiveAnalyticsScope::empty();
        }

        if ($user->seesFullExecutiveDashboard()) {
            return ExecutiveAnalyticsScope::full();
        }

        if (! $user->isBoardMember() || ! $user->board_member_id) {
            return ExecutiveAnalyticsScope::empty();
        }

        $term = $this->boardMembers->resolveTermForUser($user);
        $committees = $this->boardMembers->committeesFor($user, $term);

        return new ExecutiveAnalyticsScope(
            fullAccess: false,
            committees: $committees,
            boardMemberId: (int) $user->board_member_id,
            boardMemberName: $user->boardMember?->name,
        );
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function usingScope(ExecutiveAnalyticsScope $scope, callable $callback): mixed
    {
        $previous = $this->scope;
        $this->scope = $scope;

        try {
            return $callback();
        } finally {
            $this->scope = $previous;
        }
    }

    public function currentScope(): ExecutiveAnalyticsScope
    {
        return $this->scope;
    }

    /**
     * @return Builder<AgendaItem>
     */
    protected function agendaQuery(): Builder
    {
        $query = AgendaItem::query()->notArchived();

        if ($this->scope->isFull()) {
            return $query;
        }

        $committees = $this->scope->committees ?? collect();
        if ($committees->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $builder) use ($committees): void {
            foreach ($committees as $committee) {
                $builder->orWhere(function (Builder $nested) use ($committee): void {
                    CommitteeLookup::applyAgendaCommitteeFilter($nested, $committee);
                });
            }
        });
    }

    /**
     * @return Builder<Resolution>
     */
    protected function resolutionQuery(): Builder
    {
        $query = Resolution::query();

        if ($this->scope->isFull()) {
            return $query;
        }

        $resolutionIds = $this->agendaQuery()
            ->whereNotNull('resolution_id')
            ->pluck('resolution_id')
            ->filter()
            ->unique()
            ->values();

        $name = trim((string) ($this->scope->boardMemberName ?? ''));

        return $query->where(function (Builder $builder) use ($resolutionIds, $name): void {
            if ($resolutionIds->isNotEmpty()) {
                $builder->whereIn('id', $resolutionIds->all());
            } else {
                $builder->whereRaw('0 = 1');
            }

            if ($name !== '') {
                $builder->orWhere('sponsored_by', 'like', '%'.$name.'%');
            }
        });
    }

    /**
     * @return Builder<Ordinance>
     */
    protected function ordinanceQuery(): Builder
    {
        $query = Ordinance::query();

        if ($this->scope->isFull()) {
            return $query;
        }

        $boardMemberId = (int) ($this->scope->boardMemberId ?? 0);
        if ($boardMemberId < 1) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas(
            'boardMembers',
            fn (Builder $builder) => $builder->where('board_members.id', $boardMemberId),
        );
    }

    /**
     * @return Builder<AppropriationOrdinance>
     */
    protected function appropriationQuery(): Builder
    {
        $query = AppropriationOrdinance::query();

        if ($this->scope->isFull()) {
            return $query;
        }

        $ids = $this->agendaQuery()
            ->whereNotNull('appropriation_ordinance_id')
            ->pluck('appropriation_ordinance_id')
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('id', $ids->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(int $yearFrom, int $yearTo, int $focusYear): array
    {
        if ($yearFrom > $yearTo) {
            [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
        }

        $focusYear = max($yearFrom, min($yearTo, $focusYear));

        return [
            'kpis' => $this->kpis(),
            'agenda' => $this->agendaAnalytics($yearFrom, $yearTo, $focusYear),
            'sources' => array_merge(
                $this->sourceAnalytics($yearFrom, $yearTo),
                ['grouped' => $this->sourceAnalyticsGrouped($yearFrom, $yearTo)],
            ),
            'municipalities' => $this->municipalityAnalytics($yearFrom, $yearTo),
            'resolutions' => $this->resolutionAnalytics($yearFrom, $yearTo, $focusYear),
            'ordinances' => $this->ordinanceAnalytics($yearFrom, $yearTo, $focusYear),
            'authorship' => $this->authorshipAnalytics(),
            'appropriation' => $this->appropriationAnalytics($yearFrom, $yearTo, $focusYear),
            'sla' => $this->slaAnalytics(),
            'historical' => $this->historicalTrends($yearFrom, $yearTo),
            'legislative_output' => $this->legislativeOutputAnalytics($yearFrom, $yearTo, $focusYear),
            'heatmaps' => $this->executiveHeatmaps($yearFrom, $yearTo, $focusYear),
            'bottom' => $this->bottomRow(),
        ];
    }

    public function earliestDataYear(): int
    {
        $resolutionMin = (int) ($this->resolutionQuery()->min('series') ?? 1985);

        $agendaReceivedMin = $this->agendaQuery()->whereNotNull('date_received')->min('date_received');
        $agendaReceivedYear = $agendaReceivedMin
            ? (int) Carbon::parse($agendaReceivedMin)->format('Y')
            : 1985;

        $agendaCreatedMin = $this->agendaQuery()->min('created_at');
        $agendaCreatedYear = $agendaCreatedMin
            ? (int) Carbon::parse($agendaCreatedMin)->format('Y')
            : 1985;

        return max(1985, min($resolutionMin, $agendaReceivedYear, $agendaCreatedYear));
    }

    /**
     * @return array{municipalities: list<array<string, mixed>>, year: int, month: ?int, committee: string, committee_id: ?int, period_label: string, total: int}
     */
    public function committeeMunicipalityMap(?Committee $committee, int $year, ?int $month): array
    {
        [$start, $end] = $this->mapDateRange($year, $month);

        $municipalities = Municipality::query()
            ->orderBy('description')
            ->get()
            ->map(function (Municipality $municipality) use ($committee, $start, $end): array {
                $label = $municipality->senderLabel();
                $slug = BataanPoliticalMap::slugForName($label);

                $agendas = $this->agendaQuery()
                    ->whereNotNull('committee_referred')
                    ->where('committee_referred', '!=', '')
                    ->when(
                        $committee !== null,
                        fn ($query) => $query->tap(fn ($builder) => CommitteeLookup::applyAgendaCommitteeFilter($builder, $committee))
                    )
                    ->where(function ($query) use ($label, $municipality): void {
                        $query->where('sender', 'like', '%'.$label.'%')
                            ->orWhere('sender', 'like', '%'.$municipality->description.'%');
                    })
                    ->whereNotNull('date_passed')
                    ->whereBetween('date_passed', [$start, $end])
                    ->count();

                return [
                    'id' => $municipality->id,
                    'name' => $label,
                    'slug' => $slug,
                    'agendas' => $agendas,
                    'total' => $agendas,
                ];
            })
            ->values()
            ->all();

        return [
            'municipalities' => $municipalities,
            'year' => $year,
            'month' => $month,
            'committee' => $committee?->name ?? ($this->scope->isFull() ? 'All Committees' : 'My Committees'),
            'committee_id' => $committee?->id,
            'period_label' => $this->mapPeriodLabel($year, $month),
            'total' => (int) collect($municipalities)->sum('total'),
        ];
    }

    /**
     * @return array{by_year: list<array{year: int, resolutions: int, ordinances: int, total: int}>, by_month: list<array{month: string, resolutions: int, ordinances: int, total: int}>, focus_year: int}
     */
    public function legislativeOutputAnalytics(int $yearFrom, int $yearTo, int $focusYear): array
    {
        return [
            'by_year' => $this->legislativeOutputByYear($yearFrom, $yearTo),
            'by_month' => $this->legislativeOutputByMonth($focusYear),
            'focus_year' => $focusYear,
        ];
    }

    /**
     * @return list<array{year: int, resolutions: int, ordinances: int, total: int}>
     */
    protected function legislativeOutputByYear(int $yearFrom, int $yearTo): array
    {
        if ($this->scope->isFull()) {
            // Use series / series_year (not date_approved). Legacy imports reliably have series years;
            // date_approved is often null or set to the import date, which collapses history into one year.
            return $this->dashboard->outputByYearRange($yearFrom, $yearTo);
        }

        $years = range($yearFrom, $yearTo);

        $resolutionCounts = $this->resolutionQuery()
            ->selectRaw('series, count(*) as aggregate')
            ->whereIn('series', $years)
            ->groupBy('series')
            ->pluck('aggregate', 'series');

        $ordinanceCounts = $this->ordinanceQuery()
            ->selectRaw('series_year, count(*) as aggregate')
            ->whereIn('series_year', $years)
            ->groupBy('series_year')
            ->pluck('aggregate', 'series_year');

        $appropriationCounts = $this->appropriationQuery()
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
     * @return list<array{month: string, resolutions: int, ordinances: int, total: int}>
     */
    protected function legislativeOutputByMonth(int $year): array
    {
        if ($this->scope->isFull()) {
            return $this->dashboard->outputByMonth($year);
        }

        $monthSelect = SqlDateExpression::month('date_approved');

        $resolutionCounts = $this->resolutionQuery()
            ->selectRaw($monthSelect.' as month_no, count(*) as aggregate')
            ->where('series', $year)
            ->whereNotNull('date_approved')
            ->whereYear('date_approved', $year)
            ->groupBy('month_no')
            ->pluck('aggregate', 'month_no');

        $ordinanceCounts = $this->ordinanceQuery()
            ->selectRaw($monthSelect.' as month_no, count(*) as aggregate')
            ->where('series_year', $year)
            ->whereNotNull('date_approved')
            ->whereYear('date_approved', $year)
            ->groupBy('month_no')
            ->pluck('aggregate', 'month_no');

        $appropriationCounts = $this->appropriationQuery()
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
     * @return list<array{label: string, value: int, color: string}>
     */
    protected function agendaStatusDistribution(int $yearFrom, int $yearTo): array
    {
        $startDate = $yearFrom.'-01-01';
        $endDate = $yearTo.'-12-31';

        $counts = $this->agendaQuery()
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
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    protected function mapDateRange(int $year, ?int $month): array
    {
        if ($month !== null) {
            $month = max(1, min(12, $month));
            $start = Carbon::create($year, $month, 1)->startOfMonth();

            return [$start, $start->copy()->endOfMonth()];
        }

        return [
            Carbon::create($year, 1, 1)->startOfDay(),
            Carbon::create($year, 12, 31)->endOfDay(),
        ];
    }

    protected function mapPeriodLabel(int $year, ?int $month): string
    {
        if ($month === null) {
            return (string) $year.' (all months)';
        }

        return Carbon::create($year, $month, 1)->format('F Y');
    }

    /**
     * @return list<\App\Models\Committee>
     */
    public function mapCommitteeOptions(): \Illuminate\Support\Collection
    {
        if (! $this->scope->isFull()) {
            return ($this->scope->committees ?? collect())
                ->sortBy('sort_order')
                ->values();
        }

        return Committee::query()
            ->active()
            ->ordered()
            ->get(['id', 'name']);
    }

    public function filteredBudgetApproved(?int $year = null, ?int $municipalityId = null, string $scope = 'all'): int
    {
        $query = $this->resolutionQuery()
            ->where('status', 'approved')
            ->whereNotNull('amount');

        if ($year !== null) {
            $query->where('series', $year);
        }

        if ($municipalityId !== null) {
            $query->where('municipality_id', $municipalityId);
        }

        if ($scope === 'province') {
            $query->where('province', true);
        } elseif ($scope === 'municipal') {
            $query->where(function ($builder): void {
                $builder->where('province', false)->orWhereNull('province');
            })->whereNotNull('municipality_id');
        }

        return (int) $query->sum('amount');
    }

    /**
     * @return array<string, int|float|string>
     */
    public function kpis(): array
    {
        $today = now()->toDateString();

        return [
            'total_agenda_items' => $this->agendaQuery()->count(),
            'pending_agenda' => $this->agendaQuery()->where('status', AgendaItem::STATUS_PENDING)->count(),
            'due_today' => $this->agendaQuery()
                ->where('status', AgendaItem::STATUS_PENDING)
                ->whereDate('due_date', $today)
                ->count(),
            'lapsed_requests' => $this->agendaQuery()->where('status', AgendaItem::STATUS_LAPSED)->count(),
            'approved_resolutions' => $this->resolutionQuery()->where('status', 'approved')->count(),
            'ordinances_enacted' => $this->ordinanceQuery()->count(),
            'appropriation_ordinances' => $this->appropriationQuery()->count(),
            'urgent_requests' => $this->agendaQuery()->where('is_urgent_request', true)->count(),
            'total_budget_approved' => $this->filteredBudgetApproved(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function agendaAnalytics(int $yearFrom, int $yearTo, int $focusYear): array
    {
        $previousYear = $focusYear - 1;

        return [
            'status_distribution' => $this->agendaStatusDistribution($yearFrom, $yearTo),
            'monthly_intake' => $this->monthlyAgendaIntake($focusYear),
            'monthly_intake_comparison' => [
                'labels' => collect(range(1, 12))->map(fn (int $m) => Carbon::create(null, $m, 1)->format('M'))->all(),
                'current_year' => $focusYear,
                'previous_year' => $previousYear,
                'current' => collect($this->monthlyAgendaIntake($focusYear))->pluck('value')->all(),
                'previous' => collect($this->monthlyAgendaIntake($previousYear))->pluck('value')->all(),
            ],
            'processing_time' => $this->averageProcessingTime($yearFrom, $yearTo),
            'aging' => $this->agendaAging(),
            'due_date_health' => $this->dueDateHealth(),
            'urgent' => $this->urgentRequests(),
        ];
    }

    /**
     * @return list<array{month: string, value: int}>
     */
    public function monthlyAgendaIntake(int $year): array
    {
        $monthExpr = SqlDateExpression::month('date_received');

        $counts = $this->agendaQuery()
            ->whereNotNull('date_received')
            ->whereYear('date_received', $year)
            ->selectRaw($monthExpr.' as month_no, count(*) as aggregate')
            ->groupBy('month_no')
            ->pluck('aggregate', 'month_no');

        return $this->monthlySeries($counts);
    }

    /**
     * @return array{fastest: ?int, average: ?int, slowest: ?int, sample_size: int}
     */
    public function averageProcessingTime(int $yearFrom, int $yearTo): array
    {
        $start = $yearFrom.'-01-01';
        $end = $yearTo.'-12-31';

        $days = $this->agendaQuery()
            ->where('status', AgendaItem::STATUS_DONE)
            ->whereNotNull('date_received')
            ->whereBetween('date_received', [$start, $end])
            ->get(['date_received', 'published_at', 'date_passed', 'updated_at'])
            ->map(function (AgendaItem $item): ?int {
                $received = $item->date_received;
                $completed = $item->published_at
                    ?? $item->date_passed
                    ?? $item->updated_at;

                if (! $received || ! $completed) {
                    return null;
                }

                return max(0, $received->diffInDays($completed));
            })
            ->filter(fn (?int $days) => $days !== null)
            ->values();

        if ($days->isEmpty()) {
            return [
                'fastest' => null,
                'average' => null,
                'slowest' => null,
                'sample_size' => 0,
            ];
        }

        return [
            'fastest' => (int) $days->min(),
            'average' => (int) round($days->avg()),
            'slowest' => (int) $days->max(),
            'sample_size' => $days->count(),
        ];
    }

    /**
     * @return list<array{label: string, value: int, color: string}>
     */
    public function agendaAging(): array
    {
        $today = now()->startOfDay();
        $buckets = [
            '0–7 days' => 0,
            '8–15' => 0,
            '16–30' => 0,
            '31–60' => 0,
            '60+' => 0,
        ];

        $this->agendaQuery()
            ->where('status', AgendaItem::STATUS_PENDING)
            ->whereNotNull('date_received')
            ->get(['date_received'])
            ->each(function (AgendaItem $item) use ($today, &$buckets): void {
                $days = $item->date_received?->diffInDays($today) ?? 0;

                $key = match (true) {
                    $days <= 7 => '0–7 days',
                    $days <= 15 => '8–15',
                    $days <= 30 => '16–30',
                    $days <= 60 => '31–60',
                    default => '60+',
                };

                $buckets[$key]++;
            });

        $colors = ['#22d3ee', '#38bdf8', '#a78bfa', '#fbbf24', '#f97316'];

        return collect($buckets)
            ->values()
            ->map(fn (int $value, int $index): array => [
                'label' => array_keys($buckets)[$index],
                'value' => $value,
                'color' => $colors[$index],
            ])
            ->all();
    }

    /**
     * @return array{safe: int, near_due: int, critical: int, overdue: int}
     */
    public function dueDateHealth(): array
    {
        $today = now()->startOfDay();
        $health = [
            'safe' => 0,
            'near_due' => 0,
            'critical' => 0,
            'overdue' => 0,
        ];

        $this->agendaQuery()
            ->where('status', AgendaItem::STATUS_PENDING)
            ->whereNotNull('due_date')
            ->get(['due_date'])
            ->each(function (AgendaItem $item) use ($today, &$health): void {
                $due = $item->due_date?->startOfDay();
                if (! $due) {
                    return;
                }

                $daysLeft = $today->diffInDays($due, false);

                if ($daysLeft < 0) {
                    $health['overdue']++;
                } elseif ($daysLeft <= 7) {
                    $health['critical']++;
                } elseif ($daysLeft <= 15) {
                    $health['near_due']++;
                } else {
                    $health['safe']++;
                }
            });

        return $health;
    }

    /**
     * @return array{pending: int, completed: int, total: int, completed_percent: int}
     */
    public function urgentRequests(): array
    {
        $pending = $this->agendaQuery()
            ->where('is_urgent_request', true)
            ->where('status', AgendaItem::STATUS_PENDING)
            ->count();

        $completed = $this->agendaQuery()
            ->where('is_urgent_request', true)
            ->where('status', AgendaItem::STATUS_DONE)
            ->count();

        $total = $pending + $completed;

        return [
            'pending' => $pending,
            'completed' => $completed,
            'total' => $total,
            'completed_percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    public function sourceAnalyticsGrouped(int $yearFrom, int $yearTo): array
    {
        $start = $yearFrom.'-01-01';
        $end = $yearTo.'-12-31';

        $municipalityNames = Municipality::query()
            ->pluck('description')
            ->map(fn (string $name) => strtolower(trim($name)))
            ->all();

        $buckets = [
            'Municipalities' => 0,
            'PGO' => 0,
            'Board Members' => 0,
            'Others' => 0,
        ];

        $this->agendaQuery()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('sender')
            ->where('sender', '!=', '')
            ->pluck('sender')
            ->each(function (string $sender) use (&$buckets, $municipalityNames): void {
                $normalized = strtolower(trim($sender));

                foreach ($municipalityNames as $name) {
                    if ($name !== '' && str_contains($normalized, $name)) {
                        $buckets['Municipalities']++;

                        return;
                    }
                }

                if (str_contains($normalized, 'pgo')
                    || str_contains($normalized, 'provincial government')
                    || str_contains($normalized, 'governor')
                    || str_contains($normalized, 'gov.')
                    || str_contains($normalized, 'vg ')) {
                    $buckets['PGO']++;

                    return;
                }

                if (str_starts_with($normalized, 'bm ') || str_contains($normalized, 'board member')) {
                    $buckets['Board Members']++;

                    return;
                }

                $buckets['Others']++;
            });

        return [
            'labels' => array_keys($buckets),
            'values' => array_values($buckets),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>, most_active: ?string, least_active: ?string}
     */
    public function sourceAnalytics(int $yearFrom, int $yearTo): array
    {
        $start = $yearFrom.'-01-01';
        $end = $yearTo.'-12-31';

        $rows = $this->agendaQuery()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('sender')
            ->where('sender', '!=', '')
            ->selectRaw('sender, count(*) as aggregate')
            ->groupBy('sender')
            ->orderByDesc('aggregate')
            ->limit(12)
            ->get();

        return [
            'labels' => $rows->pluck('sender')->map(fn ($s) => Str::limit((string) $s, 24))->all(),
            'values' => $rows->pluck('aggregate')->map(fn ($v) => (int) $v)->all(),
            'most_active' => $rows->first()?->sender,
            'least_active' => $rows->last()?->sender,
        ];
    }

    /**
     * @return list<array{municipality: string, resolutions: int, agenda: int, appropriation: int, pending: int}>
     */
    public function municipalityAnalytics(int $yearFrom, int $yearTo): array
    {
        $start = $yearFrom.'-01-01';
        $end = $yearTo.'-12-31';

        $resolutionCounts = $this->resolutionQuery()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('municipality_id')
            ->selectRaw('municipality_id, count(*) as aggregate')
            ->groupBy('municipality_id')
            ->pluck('aggregate', 'municipality_id');

        $municipalities = Municipality::query()->orderBy('description')->get(['id', 'description']);

        return $municipalities
            ->map(function (Municipality $municipality) use ($resolutionCounts, $start, $end): array {
                $label = $municipality->senderLabel();
                $senderPattern = $label;

                $agendaCount = $this->agendaQuery()
                    ->whereBetween('created_at', [$start, $end])
                    ->where(function ($query) use ($senderPattern, $municipality): void {
                        $query->where('sender', 'like', '%'.$senderPattern.'%')
                            ->orWhere('sender', 'like', '%'.$municipality->description.'%');
                    })
                    ->count();

                $pending = $this->agendaQuery()
                    ->where('status', AgendaItem::STATUS_PENDING)
                    ->where(function ($query) use ($senderPattern, $municipality): void {
                        $query->where('sender', 'like', '%'.$senderPattern.'%')
                            ->orWhere('sender', 'like', '%'.$municipality->description.'%');
                    })
                    ->count();

                $appropriation = $this->appropriationQuery()
                    ->whereHas('agendaItem', function ($query) use ($senderPattern, $municipality): void {
                        $query->where('sender', 'like', '%'.$senderPattern.'%')
                            ->orWhere('sender', 'like', '%'.$municipality->description.'%');
                    })
                    ->count();

                return [
                    'municipality' => $label,
                    'resolutions' => (int) ($resolutionCounts[$municipality->id] ?? 0),
                    'agenda' => $agendaCount,
                    'appropriation' => $appropriation,
                    'pending' => $pending,
                ];
            })
            ->filter(fn (array $row): bool => ($row['resolutions'] + $row['agenda'] + $row['appropriation'] + $row['pending']) > 0)
            ->sortByDesc(fn (array $row): int => $row['resolutions'] + $row['agenda'])
            ->values()
            ->take(12)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolutionAnalytics(int $yearFrom, int $yearTo, int $focusYear): array
    {
        $monthExpr = SqlDateExpression::month('date_approved');

        $monthlyApproved = $this->resolutionQuery()
            ->where('status', 'approved')
            ->where('series', $focusYear)
            ->whereNotNull('date_approved')
            ->whereYear('date_approved', $focusYear)
            ->selectRaw($monthExpr.' as month_no, count(*) as aggregate')
            ->groupBy('month_no')
            ->pluck('aggregate', 'month_no');

        $categories = $this->resolutionQuery()
            ->whereBetween('series', [$yearFrom, $yearTo])
            ->whereNotNull('category_id')
            ->selectRaw('category_id, count(*) as aggregate')
            ->groupBy('category_id')
            ->orderByDesc('aggregate')
            ->limit(10)
            ->get()
            ->map(function ($row): array {
                $cat = Category::query()->find($row->category_id);

                return [
                    'label' => $cat?->description ?? 'Unknown',
                    'value' => (int) $row->aggregate,
                ];
            })
            ->all();

        $departments = $this->resolutionQuery()
            ->whereBetween('series', [$yearFrom, $yearTo])
            ->whereNotNull('department_id')
            ->selectRaw('department_id, count(*) as aggregate')
            ->groupBy('department_id')
            ->orderByDesc('aggregate')
            ->limit(10)
            ->get()
            ->map(function ($row): array {
                $dept = Department::query()->find($row->department_id);

                return [
                    'label' => Str::limit($dept?->description ?? 'Unknown', 28),
                    'value' => (int) $row->aggregate,
                ];
            })
            ->all();

        $committees = $this->resolutionQuery()
            ->whereBetween('series', [$yearFrom, $yearTo])
            ->whereNotNull('committee')
            ->where('committee', '!=', '')
            ->selectRaw('committee, count(*) as aggregate')
            ->groupBy('committee')
            ->orderByDesc('aggregate')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'label' => Str::limit((string) $row->committee, 28),
                'value' => (int) $row->aggregate,
            ])
            ->all();

        $sponsors = $this->resolutionQuery()
            ->whereBetween('series', [$yearFrom, $yearTo])
            ->whereNotNull('sponsored_by')
            ->where('sponsored_by', '!=', '')
            ->selectRaw('sponsored_by, count(*) as aggregate')
            ->groupBy('sponsored_by')
            ->orderByDesc('aggregate')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'label' => Str::limit((string) $row->sponsored_by, 28),
                'value' => (int) $row->aggregate,
            ])
            ->all();

        $provinceWide = $this->resolutionQuery()->whereBetween('series', [$yearFrom, $yearTo])->where('province', true)->count();
        $municipal = $this->resolutionQuery()->whereBetween('series', [$yearFrom, $yearTo])->where('province', false)->count();

        $totalBudget = (int) $this->resolutionQuery()
            ->whereBetween('series', [$yearFrom, $yearTo])
            ->where('status', 'approved')
            ->whereNotNull('amount')
            ->sum('amount');

        $budgetByDepartment = $this->resolutionQuery()
            ->whereBetween('series', [$yearFrom, $yearTo])
            ->where('status', 'approved')
            ->whereNotNull('amount')
            ->whereNotNull('department_id')
            ->selectRaw('department_id, sum(amount) as total')
            ->groupBy('department_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function ($row): array {
                $dept = Department::query()->find($row->department_id);

                return [
                    'label' => Str::limit($dept?->description ?? 'Unknown', 28),
                    'value' => (int) $row->total,
                ];
            })
            ->all();

        $budgetByMunicipality = $this->resolutionQuery()
            ->whereBetween('series', [$yearFrom, $yearTo])
            ->where('status', 'approved')
            ->whereNotNull('amount')
            ->whereNotNull('municipality_id')
            ->selectRaw('municipality_id, sum(amount) as total')
            ->groupBy('municipality_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function ($row): array {
                $mun = Municipality::query()->find($row->municipality_id);

                return [
                    'label' => $mun?->senderLabel() ?? 'Unknown',
                    'value' => (int) $row->total,
                ];
            })
            ->all();

        $monthlyBudget = $this->resolutionQuery()
            ->where('series', $focusYear)
            ->where('status', 'approved')
            ->whereNotNull('amount')
            ->whereNotNull('date_approved')
            ->whereYear('date_approved', $focusYear)
            ->selectRaw($monthExpr.' as month_no, sum(amount) as total')
            ->groupBy('month_no')
            ->pluck('total', 'month_no');

        $monthlyBudgetSeries = collect(range(1, 12))
            ->map(fn (int $month): int => (int) ($monthlyBudget[$month] ?? 0))
            ->all();

        $outputByYear = $this->legislativeOutputByYear($yearFrom, $yearTo);

        return [
            'monthly_approved' => $this->monthlySeries($monthlyApproved),
            'categories' => $categories,
            'departments' => $departments,
            'committees' => $committees,
            'sponsors' => $sponsors,
            'scope' => [
                'province_wide' => $provinceWide,
                'municipal' => $municipal,
            ],
            'budget' => [
                'total' => $totalBudget,
                'by_department' => $budgetByDepartment,
                'by_municipality' => $budgetByMunicipality,
                'monthly' => $monthlyBudgetSeries,
            ],
            'trend' => [
                'labels' => collect($outputByYear)->pluck('year')->map(fn ($y) => (string) $y)->all(),
                'values' => collect($outputByYear)->pluck('resolutions')->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function ordinanceAnalytics(int $yearFrom, int $yearTo, int $focusYear): array
    {
        $classifications = $this->ordinanceQuery()
            ->whereBetween('series_year', [$yearFrom, $yearTo])
            ->whereNotNull('classification')
            ->where('classification', '!=', '')
            ->selectRaw('classification, count(*) as aggregate')
            ->groupBy('classification')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->classification,
                'value' => (int) $row->aggregate,
            ])
            ->all();

        $published = $this->ordinanceQuery()
            ->whereBetween('series_year', [$yearFrom, $yearTo])
            ->where('publication_status', OrdinancePublicationStatus::Published)
            ->count();

        $forPublication = $this->ordinanceQuery()
            ->whereBetween('series_year', [$yearFrom, $yearTo])
            ->where('publication_status', OrdinancePublicationStatus::ForPublication)
            ->count();

        $enacted = $this->ordinanceMonthlyCounts('date_enacted', $focusYear);
        $approved = $this->ordinanceMonthlyCounts('date_approved', $focusYear);
        $posted = $this->ordinanceMonthlyCounts('date_posted', $focusYear);
        $effective = $this->ordinanceMonthlyCounts('effectivity_date', $focusYear);

        $delays = $this->ordinanceQuery()
            ->whereBetween('series_year', [$yearFrom, $yearTo])
            ->whereNotNull('date_enacted')
            ->whereNotNull('date_published_newspaper')
            ->get(['date_enacted', 'date_published_newspaper', 'date_published_newspaper_end'])
            ->map(function (Ordinance $o) {
                $published = $o->date_published_newspaper_end ?? $o->date_published_newspaper;

                return max(0, $o->date_enacted->diffInDays($published));
            })
            ->filter();

        $effectivityHeatmap = $this->ordinanceQuery()
            ->whereBetween('series_year', [$yearFrom, $yearTo])
            ->whereNotNull('effectivity_date')
            ->selectRaw(SqlDateExpression::month('effectivity_date').' as month_no, count(*) as aggregate')
            ->groupBy('month_no')
            ->pluck('aggregate', 'month_no');

        return [
            'classifications' => $classifications,
            'publication' => [
                'published' => $published,
                'for_publication' => $forPublication,
            ],
            'timeline' => [
                'labels' => collect(range(1, 12))->map(fn ($m) => now()->setMonth($m)->format('M'))->all(),
                'enacted' => $enacted,
                'approved' => $approved,
                'posted' => $posted,
                'effective' => $effective,
            ],
            'publication_delay_avg' => $delays->isNotEmpty() ? (int) round($delays->avg()) : null,
            'effectivity_heatmap' => $this->monthlySeries($effectivityHeatmap, 'value'),
        ];
    }

    /**
     * @return list<int>
     */
    protected function ordinanceMonthlyCounts(string $column, int $year): array
    {
        $monthExpr = SqlDateExpression::month($column);

        $counts = $this->ordinanceQuery()
            ->where('series_year', $year)
            ->whereNotNull($column)
            ->whereYear($column, $year)
            ->selectRaw($monthExpr.' as month_no, count(*) as aggregate')
            ->groupBy('month_no')
            ->pluck('aggregate', 'month_no');

        return collect(range(1, 12))
            ->map(fn (int $month): int => (int) ($counts[$month] ?? 0))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function authorshipAnalytics(): array
    {
        $roleCountsQuery = DB::table('ordinance_board_member')
            ->selectRaw('board_member_id, role, count(*) as aggregate')
            ->groupBy('board_member_id', 'role')
            ->orderByDesc('aggregate');

        if (! $this->scope->isFull() && $this->scope->boardMemberId) {
            $roleCountsQuery->where('board_member_id', $this->scope->boardMemberId);
        }

        $roleCounts = $roleCountsQuery->get();

        $memberNames = DB::table('board_members')->pluck('name', 'id');

        $byMember = $roleCounts->groupBy('board_member_id');

        $authors = $byMember
            ->map(function (Collection $rows, $memberId) use ($memberNames): array {
                $author = (int) $rows->where('role', OrdinanceBoardMemberRole::Author->value)->sum('aggregate');
                $sponsor = (int) $rows->where('role', OrdinanceBoardMemberRole::Sponsor->value)->sum('aggregate');
                $both = (int) $rows->where('role', OrdinanceBoardMemberRole::AuthoredSponsored->value)->sum('aggregate');

                return [
                    'label' => Str::limit((string) ($memberNames[$memberId] ?? 'Unknown'), 24),
                    'authored' => $author + $both,
                    'sponsored' => $sponsor + $both,
                    'both' => $both,
                    'total' => $author + $sponsor + $both,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->take(10);

        $topAuthors = $authors->sortByDesc('authored')->take(5)->values()->all();
        $topSponsors = $authors->sortByDesc('sponsored')->take(5)->values()->all();

        return [
            'top_authors' => $topAuthors,
            'top_sponsors' => $topSponsors,
            'author_vs_sponsor' => $authors->take(8)->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function appropriationAnalytics(int $yearFrom, int $yearTo, int $focusYear): array
    {
        $annual = $this->appropriationQuery()
            ->whereBetween('series_year', [$yearFrom, $yearTo])
            ->whereNotNull('date_passed')
            ->selectRaw('series_year, count(*) as aggregate')
            ->groupBy('series_year')
            ->orderBy('series_year')
            ->get();

        $monthExpr = SqlDateExpression::month('date_passed');

        $monthly = $this->appropriationQuery()
            ->where('series_year', $focusYear)
            ->whereNotNull('date_passed')
            ->whereYear('date_passed', $focusYear)
            ->selectRaw($monthExpr.' as month_no, count(*) as aggregate')
            ->groupBy('month_no')
            ->pluck('aggregate', 'month_no');

        $approvalDays = $this->appropriationQuery()
            ->whereBetween('series_year', [$yearFrom, $yearTo])
            ->whereNotNull('date_received')
            ->whereNotNull('date_approved')
            ->get(['date_received', 'date_passed', 'date_approved'])
            ->map(function (AppropriationOrdinance $record): array {
                return [
                    'received_to_passed' => $record->date_received && $record->date_passed
                        ? max(0, $record->date_received->diffInDays($record->date_passed))
                        : null,
                    'passed_to_approved' => $record->date_passed && $record->date_approved
                        ? max(0, $record->date_passed->diffInDays($record->date_approved))
                        : null,
                ];
            });

        $receivedToPassed = $approvalDays->pluck('received_to_passed')->filter();
        $passedToApproved = $approvalDays->pluck('passed_to_approved')->filter();

        return [
            'annual' => [
                'labels' => $annual->pluck('series_year')->map(fn ($y) => (string) $y)->all(),
                'values' => $annual->pluck('aggregate')->map(fn ($v) => (int) $v)->all(),
            ],
            'monthly_passage' => $this->monthlySeries($monthly),
            'avg_received_to_passed' => $receivedToPassed->isNotEmpty() ? (int) round($receivedToPassed->avg()) : null,
            'avg_passed_to_approved' => $passedToApproved->isNotEmpty() ? (int) round($passedToApproved->avg()) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function slaAnalytics(): array
    {
        $prescribed = $this->agendaQuery()
            ->selectRaw('prescribed_days, count(*) as aggregate')
            ->whereNotNull('prescribed_days')
            ->groupBy('prescribed_days')
            ->orderBy('prescribed_days')
            ->get()
            ->map(fn ($row): array => [
                'label' => (int) $row->prescribed_days === 0 ? 'No due date' : ((int) $row->prescribed_days).' days',
                'value' => (int) $row->aggregate,
            ])
            ->all();

        $withDue = $this->agendaQuery()
            ->where('status', AgendaItem::STATUS_DONE)
            ->whereNotNull('due_date')
            ->get(['due_date', 'published_at', 'date_passed', 'updated_at']);

        $onTime = 0;
        $late = 0;

        foreach ($withDue as $item) {
            $completed = $item->published_at ?? $item->date_passed ?? $item->updated_at;
            if (! $completed) {
                continue;
            }

            if ($completed->startOfDay()->lte($item->due_date->startOfDay())) {
                $onTime++;
            } else {
                $late++;
            }
        }

        $slaTotal = $onTime + $late;

        $avgDaysRemaining = $this->agendaQuery()
            ->where('status', AgendaItem::STATUS_PENDING)
            ->whereNotNull('due_date')
            ->get(['due_date'])
            ->map(fn (AgendaItem $item) => now()->startOfDay()->diffInDays($item->due_date->startOfDay(), false))
            ->filter(fn (int $days) => $days >= 0);

        return [
            'prescribed_days' => $prescribed,
            'compliance_percent' => $slaTotal > 0 ? (int) round(($onTime / $slaTotal) * 100) : 0,
            'on_time' => $onTime,
            'late_completion' => $late,
            'avg_days_remaining' => $avgDaysRemaining->isNotEmpty() ? (int) round($avgDaysRemaining->avg()) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function historicalTrends(int $yearFrom, int $yearTo): array
    {
        $outputByYear = $this->legislativeOutputByYear($yearFrom, $yearTo);

        $agendaGrowth = $this->agendaQuery()
            ->whereBetween('created_at', [$yearFrom.'-01-01', $yearTo.'-12-31'])
            ->selectRaw(SqlDateExpression::year('created_at').' as year_no, count(*) as aggregate')
            ->groupBy('year_no')
            ->pluck('aggregate', 'year_no');

        $years = range($yearFrom, $yearTo);

        return [
            'labels' => collect($years)->map(fn ($y) => (string) $y)->all(),
            'resolutions' => collect($outputByYear)->pluck('resolutions')->all(),
            'ordinances' => collect($outputByYear)->pluck('ordinances')->all(),
            'agenda' => collect($years)->map(fn (int $y) => (int) ($agendaGrowth[$y] ?? 0))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function executiveHeatmaps(int $yearFrom, int $yearTo, int $focusYear): array
    {
        $monthAgenda = $this->monthlyAgendaIntake($focusYear);

        $committeeResolutions = $this->resolutionQuery()
            ->whereBetween('series', [$yearFrom, $yearTo])
            ->whereNotNull('committee')
            ->where('committee', '!=', '')
            ->selectRaw('committee, count(*) as aggregate')
            ->groupBy('committee')
            ->orderByDesc('aggregate')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'label' => Str::limit((string) $row->committee, 24),
                'value' => (int) $row->aggregate,
            ])
            ->all();

        return [
            'month_agenda' => $monthAgenda,
            'committee_resolutions' => $committeeResolutions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function bottomRow(): array
    {
        $funnel = [
            ['label' => 'Intake', 'value' => $this->agendaQuery()->whereNotNull('date_received')->count()],
            ['label' => 'Committee', 'value' => $this->agendaQuery()->whereNotNull('committee_referred')->count()],
            ['label' => 'Output', 'value' => $this->agendaQuery()->where(function ($query): void {
                $query->whereNotNull('reso_ord_ao_no')
                    ->orWhere('status', AgendaItem::STATUS_DONE);
            })->count()],
            ['label' => 'Published', 'value' => $this->agendaQuery()->where(function ($q): void {
                $q->whereNotNull('published_at')
                    ->orWhereNotNull('resolution_id')
                    ->orWhereNotNull('ordinance_id')
                    ->orWhereNotNull('appropriation_ordinance_id');
            })->count()],
        ];

        $dueCalendar = $this->agendaQuery()
            ->where('status', AgendaItem::STATUS_PENDING)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->orderBy('due_date')
            ->limit(12)
            ->get(['tracking_no', 'title', 'due_date', 'sender'])
            ->map(fn (AgendaItem $item): array => [
                'label' => $item->displayLabel(),
                'title' => Str::limit((string) $item->title, 48),
                'due_date' => $item->due_date?->format('M j'),
                'sender' => $item->sender,
            ])
            ->all();

        $recentActivityQuery = ActivityLog::query()
            ->with('user')
            ->latest('created_at');

        if (! $this->scope->isFull()) {
            $agendaIds = $this->agendaQuery()->select('id');
            $recentActivityQuery
                ->where('subject_type', AgendaItem::class)
                ->whereIn('subject_id', $agendaIds);
        }

        $recentActivity = $recentActivityQuery
            ->limit(8)
            ->get()
            ->map(fn (ActivityLog $log): array => [
                'action' => \App\Support\ActivityLogPresenter::label($log),
                'user' => $log->user?->name,
                'when' => $log->created_at?->diffForHumans(),
            ])
            ->all();

        $topSponsors = $this->resolutionQuery()
            ->whereNotNull('sponsored_by')
            ->where('sponsored_by', '!=', '')
            ->selectRaw('sponsored_by, count(*) as aggregate')
            ->groupBy('sponsored_by')
            ->orderByDesc('aggregate')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->sponsored_by,
                'value' => (int) $row->aggregate,
            ])
            ->all();

        $authorship = $this->authorshipAnalytics();

        return [
            'funnel' => $funnel,
            'due_calendar' => $dueCalendar,
            'recent_activity' => $recentActivity,
            'top_sponsors' => $topSponsors,
            'top_authors' => $authorship['top_authors'],
        ];
    }

    /**
     * @param  Collection<int|string, mixed>  $counts
     * @return list<array{month: string, value: int}>
     */
    protected function monthlySeries(Collection $counts, string $valueKey = 'value'): array
    {
        return collect(range(1, 12))
            ->map(function (int $month) use ($counts, $valueKey): array {
                return [
                    'month' => Carbon::create(null, $month, 1)->format('M'),
                    $valueKey => (int) ($counts[$month] ?? 0),
                ];
            })
            ->all();
    }

    public function formatBudget(int $amount): string
    {
        if ($amount >= 1_000_000_000) {
            return '₱'.number_format($amount / 1_000_000_000, 2).' Billion';
        }

        if ($amount >= 1_000_000) {
            return '₱'.number_format($amount / 1_000_000, 2).' Million';
        }

        return '₱'.number_format($amount);
    }
}
