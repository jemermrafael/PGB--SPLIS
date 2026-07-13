@extends('layouts.app')

@section('title', $incoming->displayLabel().' — Incoming — '.config('app.name'))

@section('content')
<div class="max-w-6xl">
    <div class="splis-page-header !mb-6">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2">
                @if ($incoming->isLinked())
                    <span class="splis-badge-linked">Linked</span>
                @else
                    <span class="splis-badge-unlinked">Unlinked</span>
                @endif
                <span class="splis-badge-doc-type splis-badge-doc-type--resolution capitalize">{{ $incoming->source }}</span>
                @if ($incoming->legacy_file_id)
                    <span class="text-sm text-slate-500">sptrack File #{{ $incoming->legacy_file_id }}</span>
                @endif
            </div>
            <h1 class="splis-page-title">{{ $incoming->displayLabel() }}</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('publish', $incoming)
                <a href="{{ route('incoming.publish', $incoming) }}" class="splis-btn-primary">Publish to Resolution</a>
            @endcan
            @can('update', $incoming)
                <a href="{{ route('incoming.edit', $incoming) }}" class="splis-btn-secondary">Edit</a>
            @endcan
            <a href="{{ route('incoming.index') }}" class="splis-btn-secondary">Back to list</a>
        </div>
    </div>

    @if ($incoming->agendaItem)
        <div class="splis-alert-success mb-6">
            From agenda item
            <a href="{{ route('agenda.show', $incoming->agendaItem) }}" class="font-semibold underline">
                {{ $incoming->agendaItem->displayLabel() }}
            </a>.
        </div>
    @endif

    @if ($incoming->isLinked() && $incoming->resolution)
        <div class="splis-alert-success mb-6">
            Linked to resolution
            <a href="{{ route('resolutions.show', $incoming->resolution) }}" class="font-semibold underline">
                {{ $incoming->resolution->resolution_no }}
            </a>
            (Series {{ $incoming->resolution->series }}).
        </div>
    @endif

    <div class="splis-detail-with-sidebar">
        <div class="splis-card min-w-0">
            <div class="splis-card-header">
                <h2 class="splis-card-title">Incoming Details</h2>
            </div>
            <dl>
                @foreach ([
                    'Municipal Resolution No.' => $incoming->mun_resolution_no,
                    'Date Received' => $incoming->date_received?->format('M d, Y'),
                    'Municipal Series' => $incoming->mun_series,
                    'Municipality' => $incoming->municipality,
                    'Title' => $incoming->title,
                    'Action Taken' => $incoming->action_taken,
                    'Referral' => $incoming->referral,
                    'Agenda' => $incoming->agenda,
                    'Status' => $incoming->workflow_status,
                    'SP Resolution No.' => $incoming->sp_res_no,
                    'SP Series' => $incoming->sp_series,
                    'SP Title' => $incoming->sp_title,
                    'SP Date Approved' => $incoming->sp_date_approved?->format('M d, Y'),
                    'Concerned Agency' => $incoming->concerned_agency,
                    'Remarks' => $incoming->remarks,
                    'Created by' => $incoming->creator?->name,
                ] as $label => $value)
                    @if ($value !== null && $value !== '')
                        <div class="splis-detail-row">
                            <dt class="splis-detail-label">{{ $label }}</dt>
                            <dd class="splis-detail-value">{{ $value }}</dd>
                        </div>
                    @endif
                @endforeach
                @if ($incoming->keyword)
                    <div class="splis-detail-row">
                        <dt class="splis-detail-label">Keyword</dt>
                        <dd class="splis-detail-value">
                            @include('partials.keyword-links', [
                                'value' => $incoming->keyword,
                                'searchUrl' => route('incoming.index'),
                            ])
                        </dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="splis-detail-sidebar-column">
            @if (auth()->user()->canAdmin())
            <aside class="splis-card">
                <div class="splis-card-header">
                    <h2 class="splis-card-title">Activity Logs</h2>
                </div>
                <div class="splis-card-body space-y-5">
                    <div class="splis-activity-log-entry">
                        <p class="splis-detail-label">Record Modified</p>
                        <p class="splis-activity-log-date">
                            {{ $incoming->sp_rec_modified?->format('M d, Y g:i A') ?? '—' }}
                        </p>
                        @if ($incoming->sp_rec_modified_by)
                            <p class="splis-activity-log-by">by {{ $incoming->sp_rec_modified_by }}</p>
                        @endif
                    </div>
                    <div class="splis-activity-log-entry">
                        <p class="splis-detail-label">Record Created</p>
                        <p class="splis-activity-log-date">
                            {{ $incoming->sp_rec_added?->format('M d, Y g:i A') ?? '—' }}
                        </p>
                        @if ($incoming->sp_rec_added_by)
                            <p class="splis-activity-log-by">by {{ $incoming->sp_rec_added_by }}</p>
                        @endif
                    </div>

                    @include('incoming.partials.splis-activity-logs', ['splisActivityLogs' => $splisActivityLogs])
                </div>
            </aside>
            @endif

            @if ($incoming->mun_pdf_url || $incoming->sp_pdf_url)
                <div class="splis-card">
                    <div class="splis-card-header">
                        <h2 class="splis-card-title">PDF Links</h2>
                    </div>
                    <div class="splis-card-body flex flex-wrap gap-3">
                        @if ($incoming->mun_pdf_url)
                            <a href="{{ $incoming->mun_pdf_url }}" target="_blank" rel="noopener" class="splis-btn-secondary text-sm">Municipal PDF</a>
                        @endif
                        @if ($incoming->sp_pdf_url)
                            <a href="{{ $incoming->sp_pdf_url }}" target="_blank" rel="noopener" class="splis-btn-secondary text-sm">SP PDF</a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    @can('link', $incoming)
        <div class="splis-card mt-6" id="incoming-link-panel" data-search-url="{{ route('incoming.resolutions.search') }}" data-fallback-queries='@json(array_values(array_filter([$incoming->sp_res_no, $incoming->mun_resolution_no])))'>
            <div class="splis-card-header">
                <h2 class="splis-card-title">Link to Resolution</h2>
                <p class="text-sm text-slate-500">Sets IDs only — resolution fields are not changed.</p>
            </div>
            <div class="splis-card-body space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="md:col-span-2 space-y-2">
                        <p class="splis-page-title !text-xl md:!text-2xl">{{ $incoming->displayLabel() }}</p>
                        <label class="splis-label" for="link-resolution-q">Search Resolution</label>
                        <input
                            type="text"
                            id="link-resolution-q"
                            class="splis-input"
                            placeholder="Resolution number or title…"
                            autocomplete="off"
                            value="{{ $incoming->sp_title ?: $incoming->title }}"
                        >
                    </div>
                    <div>
                        <label class="splis-label" for="link-resolution-series">Series (optional)</label>
                        <input type="number" id="link-resolution-series" class="splis-input" placeholder="Year" min="1900" max="2100" value="{{ $incoming->sp_series }}">
                    </div>
                </div>

                <div id="link-resolution-results" class="hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"></div>

                <form method="POST" action="{{ route('incoming.link', $incoming) }}" id="incoming-link-form" class="hidden">
                    @csrf
                    <input type="hidden" name="resolution_id" id="link-resolution-id">
                    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-brand-200 bg-brand-50/50 px-4 py-3 dark:border-brand-800 dark:bg-brand-950/30">
                        <p class="flex-1 text-sm text-slate-700 dark:text-slate-200">
                            Selected: <span id="link-resolution-label" class="font-semibold"></span>
                        </p>
                        <button type="submit" class="splis-btn-primary">Link</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @include('partials.detail-prev-next', [
        'previous' => $previousIncoming,
        'next' => $nextIncoming,
        'previousUrl' => $previousIncoming ? route('incoming.show', $previousIncoming) : null,
        'nextUrl' => $nextIncoming ? route('incoming.show', $nextIncoming) : null,
        'previousLabel' => $previousIncoming?->displayLabel(),
        'nextLabel' => $nextIncoming?->displayLabel(),
        'label' => 'Incoming navigation',
    ])
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const panel = document.getElementById('incoming-link-panel');
    if (!panel) return;

    const searchUrl = panel.dataset.searchUrl;
    let fallbackQueries = [];
    try {
        fallbackQueries = JSON.parse(panel.dataset.fallbackQueries || '[]');
    } catch {
        fallbackQueries = [];
    }
    const qInput = document.getElementById('link-resolution-q');
    const seriesInput = document.getElementById('link-resolution-series');
    const resultsEl = document.getElementById('link-resolution-results');
    const form = document.getElementById('incoming-link-form');
    const idInput = document.getElementById('link-resolution-id');
    const labelEl = document.getElementById('link-resolution-label');
    let debounce = null;

    function selectResolution(item) {
        idInput.value = item.id;
        labelEl.textContent = item.resolution_no + ' — ' + (item.resolution_title || 'No title') + ' (' + item.series + ')';
        form.classList.remove('hidden');
        resultsEl.classList.add('hidden');
        qInput.value = item.resolution_no;
    }

    function renderResults(items) {
        if (!items.length) {
            resultsEl.innerHTML = '<p class="px-4 py-3 text-sm text-slate-500">No matching resolutions (already linked items are excluded).</p>';
            resultsEl.classList.remove('hidden');
            return;
        }

        resultsEl.innerHTML = items.map(function (item) {
            const title = item.resolution_title ? item.resolution_title : 'No title';
            const date = item.date_approved ? ' · ' + item.date_approved : '';
            return '<button type="button" class="incoming-link-result w-full px-4 py-3 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" data-id="' + item.id + '">' +
                '<span class="font-semibold text-slate-900 dark:text-slate-100">' + item.resolution_no + '</span>' +
                '<span class="text-slate-500"> · Series ' + item.series + date + '</span>' +
                '<span class="mt-0.5 block text-slate-600 dark:text-slate-300">' + title + '</span>' +
                '</button>';
        }).join('');
        resultsEl.classList.remove('hidden');

        resultsEl.querySelectorAll('.incoming-link-result').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const id = parseInt(btn.dataset.id, 10);
                const item = items.find(function (i) { return i.id === id; });
                if (item) selectResolution(item);
            });
        });
    }

    function fetchResults(q) {
        const params = new URLSearchParams({ q: q });
        if (seriesInput.value) params.set('series', seriesInput.value);

        return fetch(searchUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) { return r.json(); });
    }

    function search() {
        const q = qInput.value.trim();
        if (q.length < 2) {
            resultsEl.classList.add('hidden');
            return;
        }

        fetchResults(q)
            .then(renderResults)
            .catch(function () {
                resultsEl.innerHTML = '<p class="px-4 py-3 text-sm text-red-600">Search failed.</p>';
                resultsEl.classList.remove('hidden');
            });
    }

    function autoSearch() {
        const queries = [qInput.value.trim()]
            .concat(fallbackQueries.map(function (value) { return String(value || '').trim(); }))
            .filter(function (value, index, list) {
                return value.length >= 2 && list.indexOf(value) === index;
            });

        if (!queries.length) {
            return;
        }

        function tryQuery(index) {
            fetchResults(queries[index])
                .then(function (items) {
                    if (items.length || index >= queries.length - 1) {
                        renderResults(items);
                        return;
                    }

                    tryQuery(index + 1);
                })
                .catch(function () {
                    resultsEl.innerHTML = '<p class="px-4 py-3 text-sm text-red-600">Search failed.</p>';
                    resultsEl.classList.remove('hidden');
                });
        }

        tryQuery(0);
    }

    qInput.addEventListener('input', function () {
        clearTimeout(debounce);
        form.classList.add('hidden');
        debounce = setTimeout(search, 300);
    });

    seriesInput.addEventListener('change', function () {
        if (qInput.value.trim().length >= 2) search();
    });

    if (qInput.value.trim().length >= 2) {
        autoSearch();
    }
});
</script>
@endpush
