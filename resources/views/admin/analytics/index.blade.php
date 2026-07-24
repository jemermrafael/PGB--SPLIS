@extends('layouts.app')

@section('full_width', '1')
@section('title', 'Executive Dashboard — '.config('app.name'))

@section('content')
@php
    $agenda = $chartPayload['agenda'] ?? [];
    $resolutions = $chartPayload['resolutions'] ?? [];
    $heatmaps = $chartPayload['heatmaps'] ?? [];
    $statusTotal = collect($agenda['status_distribution'] ?? [])->sum('value');
    $categoryTotal = collect($resolutions['categories'] ?? [])->sum('value');
    $committeeMunicipalities = $committeeMap['municipalities'] ?? [];
    $committeeMapTotal = (int) ($committeeMap['total'] ?? collect($committeeMunicipalities)->sum('total'));
    $legislativeOutput = $chartPayload['legislative_output'] ?? [];
    $legislativeByYear = $legislativeOutput['by_year'] ?? [];
    $monthOptions = collect(range(1, 12))->mapWithKeys(fn (int $m) => [$m => \Carbon\Carbon::create(null, $m, 1)->format('F')]);
    $kpiCards = [
        ['label' => 'Total Agenda Items', 'value' => number_format($kpis['total_agenda_items'])],
        ['label' => 'Pending', 'value' => number_format($kpis['pending_agenda'])],
        ['label' => 'Due Today', 'value' => number_format($kpis['due_today'])],
        ['label' => 'Lapsed Requests', 'value' => number_format($kpis['lapsed_requests'])],
        ['label' => 'Approved Resolutions', 'value' => number_format($kpis['approved_resolutions'])],
        ['label' => 'Ordinances Enacted', 'value' => number_format($kpis['ordinances_enacted'])],
        ['label' => 'Appropriation Ordinances', 'value' => number_format($kpis['appropriation_ordinances'])],
    ];
@endphp

<div id="admin-analytics-dashboard" class="splis-exec-dashboard">
    <script type="application/json" id="admin-analytics-data">@json($chartPayload)</script>

    <header class="splis-exec-header">
        <div>
            <h1 class="splis-exec-title">Executive Dashboard</h1>
            <p class="splis-exec-subtitle">Overview of Legislative Operations and Performance</p>
        </div>
        <p class="text-sm text-slate-500">{{ now()->format('M j, Y | l') }}</p>
    </header>

    <form method="GET" class="splis-exec-filters">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div>
                <label class="splis-label" for="analytics-year-from">Year from</label>
                <input id="analytics-year-from" type="number" min="{{ $minYear }}" max="2100" name="year_from" value="{{ $yearFrom }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="analytics-year-to">Year to</label>
                <input id="analytics-year-to" type="number" min="{{ $minYear }}" max="2100" name="year_to" value="{{ $yearTo }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="analytics-focus-year">Focus Year</label>
                <input id="analytics-focus-year" type="number" min="{{ $minYear }}" max="2100" name="focus_year" value="{{ $focusYear }}" class="splis-input">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="splis-btn-primary">Apply filters</button>
                <a href="{{ route('admin.analytics.index') }}" class="splis-btn-ghost">Reset filters</a>
            </div>
        </div>
    </form>

    <div class="splis-exec-kpi-grid">
        @foreach ($kpiCards as $kpi)
            <div class="splis-exec-kpi">
                <p class="splis-exec-kpi-value">{{ $kpi['value'] }}</p>
                <p class="splis-exec-kpi-label">{{ $kpi['label'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="splis-exec-panel">
            <h2 class="splis-exec-panel-title">Legislative Output by Year</h2>
            <p class="splis-exec-panel-subtitle">Resolutions vs Ordinances (includes Appropriation Ordinances) · by date approved</p>
            <div class="splis-exec-chart-wrap">
                <canvas id="chart-legislative-output-year" aria-label="Legislative output by year"></canvas>
            </div>
        </div>

        <div class="splis-exec-panel">
            <h2 class="splis-exec-panel-title">Monthly Output — {{ $focusYear }}</h2>
            <p class="splis-exec-panel-subtitle">Based on approved dates (Resolutions + Ordinances, including Appropriation Ordinances)</p>
            <div class="splis-exec-chart-wrap">
                <canvas id="chart-legislative-output-month" aria-label="Monthly legislative output"></canvas>
            </div>
        </div>
    </div>

    <div class="splis-exec-chart-grid">
        <div class="splis-exec-panel">
            <h2 class="splis-exec-panel-title">Agenda Status Distribution</h2>
            <p class="splis-exec-panel-subtitle">{{ number_format($statusTotal) }} total items · green done, yellow pending, gray no due date</p>
            <div class="splis-exec-chart-wrap splis-exec-chart-wrap--donut">
                <canvas id="chart-agenda-status" aria-label="Agenda status distribution"></canvas>
            </div>
        </div>

        <div class="splis-exec-panel">
            <h2 class="splis-exec-panel-title">Monthly Agenda Intake</h2>
            <p class="splis-exec-panel-subtitle">{{ ($agenda['monthly_intake_comparison']['previous_year'] ?? $focusYear - 1) }} vs {{ $focusYear }}</p>
            <div class="splis-exec-chart-wrap">
                <canvas id="chart-agenda-intake" aria-label="Monthly agenda intake"></canvas>
            </div>
        </div>

        <div class="splis-exec-panel">
            <h2 class="splis-exec-panel-title">Due Date Health</h2>
            <p class="splis-exec-panel-subtitle">Safe · Near due · Critical · Overdue</p>
            <div class="splis-exec-chart-wrap">
                <canvas id="chart-due-date-health" aria-label="Due date health"></canvas>
            </div>
        </div>

        <div class="splis-exec-panel">
            <h2 class="splis-exec-panel-title">Agenda Aging — Pending Only</h2>
            <p class="splis-exec-panel-subtitle">Days since received</p>
            <div class="splis-exec-chart-wrap">
                <canvas id="chart-agenda-aging" aria-label="Agenda aging"></canvas>
            </div>
        </div>
    </div>

    <div class="splis-exec-chart-grid">
        <div class="splis-exec-panel">
            <h2 class="splis-exec-panel-title">Requests by Sender</h2>
            <p class="splis-exec-panel-subtitle">Grouped Source Analytics</p>
            <div class="splis-exec-chart-wrap">
                <canvas id="chart-source-senders" aria-label="Requests by sender"></canvas>
            </div>
        </div>

        <div class="splis-exec-panel">
            <h2 class="splis-exec-panel-title">Resolutions Trend</h2>
            <p class="splis-exec-panel-subtitle">Monthly Approved — {{ $focusYear }}</p>
            <div class="splis-exec-chart-wrap">
                <canvas id="chart-resolution-trend" aria-label="Resolutions trend"></canvas>
            </div>
        </div>

        <div class="splis-exec-panel">
            <h2 class="splis-exec-panel-title">Resolutions by Category</h2>
            <p class="splis-exec-panel-subtitle">{{ number_format($categoryTotal) }} total Resolutions</p>
            <div class="splis-exec-chart-wrap splis-exec-chart-wrap--donut">
                <canvas id="chart-resolution-categories" aria-label="Resolutions by category"></canvas>
            </div>
        </div>

        <div class="splis-exec-panel">
            <h2 class="splis-exec-panel-title">Committee Workload</h2>
            <p class="splis-exec-panel-subtitle">Resolutions handled by Committee</p>
            <div class="splis-exec-chart-wrap">
                <canvas id="chart-committee-workload" aria-label="Committee workload"></canvas>
            </div>
        </div>
    </div>

    <div class="splis-exec-heatmaps-map-grid">
        <div class="splis-exec-heatmaps-column">
            <div class="mb-2">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Executive Heatmaps</h2>
                <p class="text-sm text-slate-500">Peak months and committee resolution load</p>
            </div>

            <div class="flex flex-col gap-4">
                @include('admin.analytics.partials.heatmap', [
                    'title' => 'Month × Agenda Volume',
                    'subtitle' => 'Focus year '.$focusYear,
                    'cells' => collect($heatmaps['month_agenda'] ?? [])->map(fn ($r) => ['label' => $r['month'], 'value' => $r['value']])->all(),
                ])
                @include('admin.analytics.partials.heatmap', [
                    'title' => 'Committee × Resolution Count',
                    'subtitle' => $yearFrom.' – '.$yearTo,
                    'cells' => $heatmaps['committee_resolutions'] ?? [],
                ])
            </div>
        </div>

        <div
            id="committee-municipality-map"
            class="splis-exec-panel"
            data-map-url="{{ route('admin.analytics.municipality-map') }}"
            data-min-year="{{ $minYear }}"
        >
            <div class="mb-4">
                <h2 class="splis-exec-panel-title">Bataan — Agendas by Municipality</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Agendas counted by date passed</p>
                <p class="splis-exec-panel-subtitle" data-map-subtitle>
                    {{ $committeeMap['committee'] ?? 'All Committees' }} · {{ $committeeMap['period_label'] ?? '' }} · {{ number_format($committeeMapTotal) }} agendas
                </p>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="splis-label" for="map-committee-id">Committee</label>
                    <select id="map-committee-id" class="splis-input" data-map-filter="committee_id">
                        <option value="" @selected($mapCommitteeId === null)>All Committees</option>
                        @foreach ($committees as $committee)
                            <option value="{{ $committee->id }}" @selected($mapCommitteeId === $committee->id)>{{ $committee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="splis-label" for="map-year">Year</label>
                    <input id="map-year" type="number" min="{{ $minYear }}" max="2100" value="{{ $mapYear }}" class="splis-input" data-map-filter="year">
                </div>
                <div>
                    <label class="splis-label" for="map-month">Month</label>
                    <select id="map-month" class="splis-input" data-map-filter="month">
                        <option value="" @selected($mapMonth === null)>All months</option>
                        @foreach ($monthOptions as $value => $label)
                            <option value="{{ $value }}" @selected($mapMonth === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-6" data-map-canvas>
                @include('admin.analytics.partials.bataan-political-map', [
                    'municipalities' => $committeeMunicipalities,
                ])
            </div>
        </div>
    </div>
</div>
@endsection
