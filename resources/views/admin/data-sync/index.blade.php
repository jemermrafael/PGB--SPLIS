@extends('layouts.app')

@section('title', 'Data sync — '.config('app.name'))

@section('content')
    <x-page-header
        title="Data Sync"
        subtitle="Refresh SPLIS from uploaded CSV files. Prefer dry run first on live data."
    />

@if ($recentLogs->isNotEmpty())
    <div class="mb-8 splis-card p-6">
        <h2 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Recent sync activity</h2>
        <p class="mb-4 text-sm text-slate-500">Last successful runs from this screen.</p>
        <ul class="space-y-3 text-sm">
            @foreach ($recentLogs as $log)
                <li class="flex flex-wrap items-baseline gap-x-2 gap-y-1 border-b border-slate-100 pb-3 last:border-0 dark:border-slate-800">
                    <span class="font-medium text-slate-800 dark:text-slate-200">
                        @switch($log->action)
                            @case('data_sync.resolutions_csv') Final resolutions @break
                            @case('data_sync.sptrack_incoming') Sptrack incoming @break
                            @case('data_sync.sptrack_resolutions') Linked resolutions @break
                            @case('data_sync.agenda_csv') Agenda tracker @break
                            @case('data_sync.ordinances_csv') Ordinances @break
                            @case('data_sync.link_pdfs') PDF path backfill @break
                            @case('data_sync.drive_mirror_rebuild') Drive mirror queue rebuild @break
                            @case('data_sync.drive_mirror_process') Drive mirror queue process @break
                            @default {{ $log->action }}
                        @endswitch
                    </span>
                    <span class="text-slate-500">{{ $log->created_at?->format('M j, Y g:i A') }}</span>
                    @if ($log->user)
                        <span class="text-slate-500">by {{ $log->user->name }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif

<p class="splis-admin-section-title">Routine syncs</p>
<p class="mb-4 text-sm text-slate-600 dark:text-slate-400">Upload a CSV for each sync. Use dry run to preview counts before applying.</p>

<div class="mb-10 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="splis-card p-6">
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Final resolutions</h2>
            <x-risk-badge level="safe" label="Safe sync" />
        </div>
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            Applies edits from an <code class="text-xs">SP_*.csv</code> export. Matches by legacy <code class="text-xs">ID</code> and updates titles, authors, categories, and dates.
        </p>
        <form method="POST" action="{{ route('admin.data-sync.resolutions') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label for="sp_csv" class="splis-label">Upload SP export CSV</label>
                <input type="file" name="sp_csv" id="sp_csv" accept=".csv,text/csv" required class="splis-input mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="dry_run" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Dry run (preview counts only)
            </label>
            <button type="submit" class="splis-btn-primary">Sync final resolutions</button>
        </form>
    </div>

    <div class="splis-card p-6">
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Agenda tracker</h2>
            <x-risk-badge level="safe" label="Safe sync" />
        </div>
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            Imports or updates agenda rows by <code class="text-xs">tracking_no</code>. Rows without tracking numbers may import as urgent requests.
        </p>
        <form method="POST" action="{{ route('admin.data-sync.agenda') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label for="agenda_csv" class="splis-label">Upload agenda CSV</label>
                <input type="file" name="agenda_csv" id="agenda_csv" accept=".csv,text/csv" required class="splis-input mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="dry_run" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Dry run
            </label>
            <button type="submit" class="splis-btn-primary">Sync agenda</button>
        </form>
    </div>

    <div class="splis-card p-6 xl:col-span-2">
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Ordinances</h2>
            <x-risk-badge level="safe" label="Safe sync" />
        </div>
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            Imports or updates ordinances from <code class="text-xs">Ordinances-*.csv</code> (e.g. <code class="text-xs">Ordinances-001.csv</code>).
            Columns: <code class="text-xs">ORD NO.</code>, <code class="text-xs">GDrive</code>, <code class="text-xs">Publish Status</code>, subject, dates, MOV bulletin/certification/newspaper links.
            Matches by ordinance number and series year.
        </p>
        <form method="POST" action="{{ route('admin.data-sync.ordinances') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="ordinances_csv" class="splis-label">Upload ordinances CSV</label>
                    <input type="file" name="ordinances_csv" id="ordinances_csv" accept=".csv,text/csv" required class="splis-input mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                </div>
                <div>
                    <label for="ordinances_series_year" class="splis-label">Series year (optional)</label>
                    <input type="number" name="series_year" id="ordinances_series_year" min="1900" max="2100" value="{{ config('ordinances.default_series_year') }}" class="splis-input mt-1 block w-full">
                    <p class="mt-1 text-xs text-slate-500">Leave as default unless importing a different series.</p>
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="dry_run" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Dry run
            </label>
            <button type="submit" class="splis-btn-primary">Sync ordinances</button>
        </form>
        <details class="mt-3 text-xs text-slate-500">
            <summary class="cursor-pointer">CLI</summary>
            <code class="mt-1 block">php artisan splis:import-ordinances-csv --path=oldsp/Ordinances-001.csv</code>
        </details>
    </div>
</div>

<p class="splis-admin-section-title">Drive PDF mirror queue</p>
<p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
    Automatically download Google Drive links into private storage for ordinances, appropriation ordinances, and agenda items.
    Rebuild scans all records with a URL but no local file; process downloads pending items.
</p>

<div class="mb-10 splis-card p-6">
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Mirror queue</h2>
        <x-risk-badge level="maintenance" label="Maintenance" />
    </div>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-700">
            <p class="text-xs uppercase tracking-wide text-slate-500">Pending</p>
            <p class="text-2xl font-semibold text-amber-600">{{ $driveMirrorStats['pending'] }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-700">
            <p class="text-xs uppercase tracking-wide text-slate-500">Processing</p>
            <p class="text-2xl font-semibold text-slate-700 dark:text-slate-200">{{ $driveMirrorStats['processing'] }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-700">
            <p class="text-xs uppercase tracking-wide text-slate-500">Completed</p>
            <p class="text-2xl font-semibold text-emerald-600">{{ $driveMirrorStats['completed'] }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-700">
            <p class="text-xs uppercase tracking-wide text-slate-500">Failed</p>
            <p class="text-2xl font-semibold text-red-600">{{ $driveMirrorStats['failed'] }}</p>
        </div>
    </div>

    <div class="mb-6 flex flex-wrap gap-3">
        <form method="POST" action="{{ route('admin.data-sync.drive-mirror.rebuild') }}">
            @csrf
            <button type="submit" class="splis-btn-secondary">Rebuild queue</button>
        </form>
        <form method="POST" action="{{ route('admin.data-sync.drive-mirror.process') }}" class="flex flex-wrap items-center gap-2">
            @csrf
            <input type="hidden" name="limit" value="5">
            <button type="submit" class="splis-btn-primary">Process next 5</button>
        </form>
        <form method="POST" action="{{ route('admin.data-sync.drive-mirror.process') }}">
            @csrf
            <input type="hidden" name="limit" value="20">
            <button type="submit" class="splis-btn-secondary">Process next 20</button>
        </form>
    </div>

    @if ($driveMirrorItems->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-700">
                        <th class="px-3 py-2">Record</th>
                        <th class="px-3 py-2">Document</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Attempts</th>
                        <th class="px-3 py-2">Queued</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($driveMirrorItems as $item)
                        <tr class="border-b border-slate-100 dark:border-slate-800">
                            <td class="px-3 py-2 font-medium text-slate-800 dark:text-slate-200">{{ $item->entityLabel() }}</td>
                            <td class="px-3 py-2">{{ $item->documentLabel() }}</td>
                            <td class="px-3 py-2">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-amber-100 text-amber-800' => $item->status === 'pending',
                                    'bg-sky-100 text-sky-800' => $item->status === 'processing',
                                ])>{{ ucfirst($item->status) }}</span>
                            </td>
                            <td class="px-3 py-2">{{ $item->attempts }}</td>
                            <td class="px-3 py-2 text-slate-500">{{ $item->queued_at?->format('M j, g:i A') ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-slate-500">No pending or processing items. Rebuild the queue to scan for Drive URLs without local files.</p>
    @endif

    <div class="mt-8">
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Failed items</h3>
            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">{{ $driveMirrorStats['failed'] }}</span>
        </div>

        @if ($driveMirrorFailedItems->isNotEmpty())
            <div class="space-y-3">
                @foreach ($driveMirrorFailedItems as $item)
                    <div class="rounded-xl border border-red-200 bg-red-50/60 p-4 dark:border-red-900/60 dark:bg-red-950/20">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-slate-900 dark:text-slate-100">{{ $item->entityLabel() }} — {{ $item->documentLabel() }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    Attempts: {{ $item->attempts }}
                                    · Last tried: {{ $item->completed_at?->format('M j, Y g:i A') ?: ($item->queued_at?->format('M j, Y g:i A') ?: '—') }}
                                </p>
                            </div>
                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Failed</span>
                        </div>

                        <div class="mt-3 grid gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Error</p>
                                <p class="mt-1 whitespace-pre-wrap break-words text-sm text-red-700 dark:text-red-300">{{ $item->error_message ?: 'Unknown error.' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Source URL</p>
                                <a href="{{ $item->source_url }}" target="_blank" rel="noopener" class="mt-1 block break-all text-sm text-brand-700 underline dark:text-brand-300">
                                    {{ $item->source_url }}
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-slate-500">No failed items right now.</p>
        @endif
    </div>

    <details class="mt-4 text-xs text-slate-500">
        <summary class="cursor-pointer">CLI</summary>
        <code class="mt-1 block">php artisan pdf-mirror:process-queue --rebuild --limit=5</code>
        <code class="mt-1 block">php artisan agenda:mirror-pdfs --limit=5</code>
    </details>
</div>

<p class="splis-admin-section-title">Maintenance</p>
<p class="mb-4 text-sm text-slate-600 dark:text-slate-400">Use after file placement or migrations. Prefer dry run first.</p>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="splis-card border-amber-200 p-6 dark:border-amber-900/60">
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Backfill resolution PDF paths</h2>
            <x-risk-badge level="maintenance" label="Maintenance" />
        </div>
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            Writes <code class="text-xs">pdf_path</code> as <code class="text-xs">resolutions/{series}/{resolution_no}.pdf</code> only — does not copy files.
            Place PDFs under <code class="text-xs">storage/app/resolutions/</code> yourself.
        </p>
        <form method="POST" action="{{ route('admin.data-sync.link-pdfs') }}" class="space-y-4">
            @csrf
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="only_missing" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Only missing (skip rows that already have pdf_path)
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="dry_run" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Dry run first (recommended)
            </label>
            <button type="submit" class="splis-btn-secondary">Backfill pdf_path</button>
        </form>
        <details class="mt-3 text-xs text-slate-500">
            <summary class="cursor-pointer">CLI</summary>
            <code class="mt-1 block">php artisan resolutions:link-pdfs --only-missing</code>
        </details>
    </div>
</div>
@endsection
