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
                            @case('data_sync.link_pdfs') PDF path backfill @break
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
