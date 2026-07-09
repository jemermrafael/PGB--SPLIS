@extends('layouts.app')

@section('title', 'Data Analytics — '.config('app.name'))

@section('content')
<div
    id="admin-analytics-dashboard"
    class="splis-hud-dashboard -mx-6 -mt-8 px-6 py-8 md:-mx-8 md:px-8"
    data-charts='@json($chartPayload)'
>
    <div class="splis-hud-dashboard-bg" aria-hidden="true"></div>

    <div class="relative z-10">
        <header class="splis-hud-header mb-6">
            <div>
                <p class="splis-hud-eyebrow">Legislative intelligence</p>
                <h1 class="splis-hud-title">Data analytics command center</h1>
                <p class="splis-hud-subtitle">Workflow, output, and committee performance — {{ $yearFrom }} to {{ $yearTo }}</p>
            </div>
            <div class="splis-hud-header-meta">
                <span class="splis-hud-meta-pill">Focus year {{ $focusYear }}</span>
                <span class="splis-hud-meta-pill">{{ now()->format('M j, Y') }}</span>
            </div>
        </header>

        <form method="GET" class="splis-hud-panel splis-hud-filters mb-6">
            <div class="splis-hud-panel-corners" aria-hidden="true"></div>
            <h2 class="splis-hud-panel-title">Filters</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div>
                    <label class="splis-hud-label" for="analytics-year-from">Year from</label>
                    <input id="analytics-year-from" type="number" min="1990" max="2100" name="year_from" value="{{ $yearFrom }}" class="splis-hud-input">
                </div>
                <div>
                    <label class="splis-hud-label" for="analytics-year-to">Year to</label>
                    <input id="analytics-year-to" type="number" min="1990" max="2100" name="year_to" value="{{ $yearTo }}" class="splis-hud-input">
                </div>
                <div>
                    <label class="splis-hud-label" for="analytics-focus-year">Focus year</label>
                    <input id="analytics-focus-year" type="number" min="1990" max="2100" name="focus_year" value="{{ $focusYear }}" class="splis-hud-input">
                </div>
                <div>
                    <label class="splis-hud-label" for="analytics-committee">Committee</label>
                    <select id="analytics-committee" name="committee_id" class="splis-hud-input">
                        <option value="">All committees</option>
                        @foreach ($committees as $committee)
                            <option value="{{ $committee->id }}" @selected((int) $committeeId === (int) $committee->id)>{{ $committee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="splis-hud-label" for="analytics-chart-limit">Top committees</label>
                    <input id="analytics-chart-limit" type="number" min="5" max="20" name="chart_limit" value="{{ $chartLimit }}" class="splis-hud-input">
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="splis-hud-btn-primary">Apply filters</button>
                <a href="{{ route('admin.analytics.index') }}" class="splis-hud-btn-ghost">Reset</a>
            </div>
        </form>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
            <a href="{{ $monitoringUrls['referred'] }}" class="splis-hud-kpi">
                <span class="splis-hud-kpi-icon splis-hud-kpi-icon--cyan">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                </span>
                <span class="splis-hud-kpi-value">{{ number_format($committeeOverview['referred']) }}</span>
                <span class="splis-hud-kpi-label">Referred</span>
            </a>
            <a href="{{ $monitoringUrls['pending'] }}" class="splis-hud-kpi">
                <span class="splis-hud-kpi-icon splis-hud-kpi-icon--amber">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </span>
                <span class="splis-hud-kpi-value">{{ number_format($committeeOverview['pending']) }}</span>
                <span class="splis-hud-kpi-label">Pending</span>
            </a>
            <a href="{{ $monitoringUrls['scheduled'] }}" class="splis-hud-kpi">
                <span class="splis-hud-kpi-icon splis-hud-kpi-icon--purple">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                </span>
                <span class="splis-hud-kpi-value">{{ number_format($committeeOverview['scheduled']) }}</span>
                <span class="splis-hud-kpi-label">Scheduled</span>
            </a>
            <a href="{{ $monitoringUrls['reports'] }}" class="splis-hud-kpi">
                <span class="splis-hud-kpi-icon splis-hud-kpi-icon--blue">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                </span>
                <span class="splis-hud-kpi-value">{{ number_format($committeeOverview['reports']) }}</span>
                <span class="splis-hud-kpi-label">Reports</span>
            </a>
            <a href="{{ $monitoringUrls['completed'] }}" class="splis-hud-kpi splis-hud-kpi--wide">
                <span class="splis-hud-kpi-icon splis-hud-kpi-icon--green">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </span>
                <span class="splis-hud-kpi-value">{{ number_format($committeeOverview['completed']) }}</span>
                <span class="splis-hud-kpi-label">Completed</span>
            </a>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
            <div class="splis-hud-panel xl:col-span-5">
                <div class="splis-hud-panel-corners" aria-hidden="true"></div>
                <div class="splis-hud-panel-head">
                    <h2 class="splis-hud-panel-title">Legislative output by year</h2>
                    <p class="splis-hud-panel-subtitle">Resolutions vs ordinances</p>
                </div>
                <div class="splis-hud-chart-wrap splis-hud-chart-wrap--tall">
                    <canvas id="chart-output-year" aria-label="Legislative output by year"></canvas>
                </div>
            </div>

            <div class="splis-hud-panel xl:col-span-4">
                <div class="splis-hud-panel-corners" aria-hidden="true"></div>
                <div class="splis-hud-panel-head">
                    <h2 class="splis-hud-panel-title">Year trend</h2>
                    <p class="splis-hud-panel-subtitle">Total output with trend line</p>
                </div>
                <div class="splis-hud-chart-wrap splis-hud-chart-wrap--tall">
                    <canvas id="chart-output-combo" aria-label="Year output trend"></canvas>
                </div>
            </div>

            <div class="splis-hud-panel xl:col-span-3">
                <div class="splis-hud-panel-corners" aria-hidden="true"></div>
                <div class="splis-hud-panel-head">
                    <h2 class="splis-hud-panel-title">Agenda status</h2>
                    <p class="splis-hud-panel-subtitle">Distribution by status</p>
                </div>
                <div class="splis-hud-chart-wrap splis-hud-chart-wrap--donut">
                    <canvas id="chart-status-donut" aria-label="Agenda status distribution"></canvas>
                </div>
                <div class="splis-hud-chart-wrap splis-hud-chart-wrap--spark mt-3">
                    <canvas id="chart-pipeline-spark" aria-label="Agenda pipeline"></canvas>
                </div>
            </div>

            <div class="splis-hud-panel xl:col-span-7">
                <div class="splis-hud-panel-corners" aria-hidden="true"></div>
                <div class="splis-hud-panel-head">
                    <h2 class="splis-hud-panel-title">Monthly output — {{ $focusYear }}</h2>
                    <p class="splis-hud-panel-subtitle">Resolutions, ordinances, and combined trend</p>
                </div>
                <div class="splis-hud-chart-wrap splis-hud-chart-wrap--tall">
                    <canvas id="chart-output-month" aria-label="Monthly output"></canvas>
                </div>
            </div>

            <div class="splis-hud-panel xl:col-span-5">
                <div class="splis-hud-panel-corners" aria-hidden="true"></div>
                <div class="splis-hud-panel-head flex items-start justify-between gap-2">
                    <div>
                        <h2 class="splis-hud-panel-title">Committee ranking</h2>
                        <p class="splis-hud-panel-subtitle">Pending vs completed by committee</p>
                    </div>
                    <a href="{{ route('committee-monitoring.index', ['view' => 'pending']) }}" class="splis-hud-link text-xs">Open monitoring</a>
                </div>
                <div class="splis-hud-chart-wrap splis-hud-chart-wrap--tall">
                    <canvas id="chart-committee-bars" aria-label="Committee workload ranking"></canvas>
                </div>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="splis-hud-mini-stat">
                <span class="splis-hud-mini-label">Pending agenda</span>
                <span class="splis-hud-mini-value">{{ number_format($agendaPipeline['pending']) }}</span>
            </div>
            <div class="splis-hud-mini-stat">
                <span class="splis-hud-mini-label">Expiring soon</span>
                <span class="splis-hud-mini-value">{{ number_format($agendaPipeline['expiring_soon']) }}</span>
            </div>
            <div class="splis-hud-mini-stat">
                <span class="splis-hud-mini-label">Published</span>
                <span class="splis-hud-mini-value">{{ number_format($agendaPipeline['published']) }}</span>
            </div>
            <div class="splis-hud-mini-stat">
                <span class="splis-hud-mini-label">On final OB</span>
                <span class="splis-hud-mini-value">{{ number_format($agendaPipeline['on_final_ob']) }}</span>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @vite('resources/js/admin-analytics.js')
@endpush
