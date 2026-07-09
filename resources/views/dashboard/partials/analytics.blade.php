<section class="mb-8">
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Workflow analytics</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">Agenda pipeline and legislative output at a glance.</p>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('agenda.index') }}" class="splis-stat splis-stat--gold splis-stat--clickable">
            <p class="splis-stat-label">Pending agenda</p>
            <p class="splis-stat-value">{{ number_format($agendaPipeline['pending']) }}</p>
            <p class="splis-stat-meta">Awaiting action</p>
        </a>
        <a href="{{ route('agenda.index') }}" class="splis-stat splis-stat--amber splis-stat--clickable">
            <p class="splis-stat-label">Expiring soon</p>
            <p class="splis-stat-value">{{ number_format($agendaPipeline['expiring_soon']) }}</p>
            <p class="splis-stat-meta">Within {{ $expiringSoonDays }} days</p>
        </a>
        <a href="{{ route('agenda.index') }}" class="splis-stat splis-stat--green splis-stat--clickable">
            <p class="splis-stat-label">Published</p>
            <p class="splis-stat-value">{{ number_format($agendaPipeline['published']) }}</p>
            <p class="splis-stat-meta">Linked to output</p>
        </a>
        <a href="{{ route('committee-monitoring.index') }}" class="splis-stat splis-stat--sky splis-stat--clickable">
            <p class="splis-stat-label">On final OB</p>
            <p class="splis-stat-value">{{ number_format($agendaPipeline['on_final_ob']) }}</p>
            <p class="splis-stat-meta">Final Order of Business</p>
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="splis-card">
            <div class="splis-card-header">
                <h3 class="splis-card-title">Legislative output by year</h3>
                <p class="splis-card-subtitle">Resolutions and ordinances per series year</p>
            </div>
            <div class="splis-card-body">
                @include('partials.analytics-bar-chart', ['rows' => $outputByYearChart])
            </div>
        </div>

        <div class="splis-card">
            <div class="splis-card-header flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="splis-card-title">Committee workload</h3>
                    <p class="splis-card-subtitle">Pending referred items by committee</p>
                </div>
                <a href="{{ route('committee-monitoring.index', ['view' => 'pending']) }}" class="splis-link text-sm">Open monitoring</a>
            </div>
            <div class="splis-card-body">
                @include('partials.analytics-bar-chart', ['rows' => $committeeWorkloadChart])
            </div>
        </div>
    </div>
</section>
