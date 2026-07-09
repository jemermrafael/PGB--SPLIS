<section class="mb-8">
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Your analytics</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">Committee workload and accomplishment trends.</p>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="splis-stat splis-stat--green">
            <p class="splis-stat-label">Done this month</p>
            <p class="splis-stat-value">{{ number_format($boardAnalytics['done_this_month']) }}</p>
            <p class="splis-stat-meta">
                @if ($boardAnalytics['done_last_month'] > 0)
                    vs {{ number_format($boardAnalytics['done_last_month']) }} last month
                @else
                    Accomplished items
                @endif
            </p>
        </div>
        <div class="splis-stat splis-stat--brand">
            <p class="splis-stat-label">Published</p>
            <p class="splis-stat-value">{{ number_format($boardAnalytics['published']) }}</p>
            <p class="splis-stat-meta">Your committee referrals</p>
        </div>
        <div class="splis-stat splis-stat--sky">
            <p class="splis-stat-label">On final OB</p>
            <p class="splis-stat-value">{{ number_format($boardAnalytics['on_final_ob']) }}</p>
            <p class="splis-stat-meta">Final session placement</p>
        </div>
        <a href="{{ route('board-member.agenda.index') }}" class="splis-stat splis-stat--gold splis-stat--clickable">
            <p class="splis-stat-label">Pending</p>
            <p class="splis-stat-value">{{ number_format($agendaStats['pending']) }}</p>
            <p class="splis-stat-meta">Awaiting action</p>
        </a>
    </div>

    @if (! empty($committeeBreakdownChart))
        <div class="splis-card">
            <div class="splis-card-header flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="splis-card-title">Pending by committee</h3>
                    <p class="splis-card-subtitle">Open items under your committee assignments</p>
                </div>
                <a href="{{ route('board-member.agenda.index') }}" class="splis-link text-sm">My agenda</a>
            </div>
            <div class="splis-card-body">
                @include('partials.analytics-bar-chart', ['rows' => $committeeBreakdownChart])
            </div>
        </div>
    @endif
</section>
