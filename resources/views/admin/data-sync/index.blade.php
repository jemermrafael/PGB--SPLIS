@extends('layouts.app')

@section('title', 'Data sync — '.config('app.name'))

@section('content')
<div class="splis-page-header">
    <div>
        <h1 class="splis-page-title">Data Sync</h1>
        <p class="splis-page-subtitle">Refresh SPLIS from oldsp CSV exports and sptrack — upload a file or use exports already on the server.</p>
    </div>
</div>

@if (session('status'))
    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
        {{ session('status') }}
    </div>
@endif

@if (session('error'))
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mb-8 grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="splis-card p-5 text-sm text-slate-600 dark:text-slate-400">
        <p class="font-medium text-slate-900 dark:text-slate-100">Resolution CSV (server fallback)</p>
        <p class="mt-1"><code class="text-xs">{{ $csvDirectory }}</code></p>
        <p class="mt-2">Newest export: <strong>{{ $spCsvFile ?? 'No SP_*.csv found' }}</strong></p>
        <p class="mt-2 text-xs">Used when no file is uploaded below.</p>
    </div>
    <div class="splis-card p-5 text-sm text-slate-600 dark:text-slate-400">
        <p class="font-medium text-slate-900 dark:text-slate-100">Sptrack source (server fallback)</p>
        <p class="mt-1">{{ $sptrackSource }}</p>
        <p class="mt-2">CSV on server: {{ $sptrackCsvExists ? 'available' : 'not found' }}</p>
        <p class="mt-2 text-xs">Used when no sptrack CSV is uploaded below.</p>
    </div>
    <div class="splis-card p-5 text-sm text-slate-600 dark:text-slate-400">
        <p class="font-medium text-slate-900 dark:text-slate-100">Agenda CSV (server fallback)</p>
        <p class="mt-1"><code class="text-xs">{{ $agendaCsvPath }}</code></p>
        <p class="mt-2">Data file: <strong>{{ $agendaCsvExists ? basename($agendaCsvPath) : 'not found' }}</strong></p>
        <p class="mt-2">PDF links fallback: <strong>{{ $agendaLinksExists ? basename($agendaLinksPath) : 'embedded in agenda CSV' }}</strong></p>
        <p class="mt-1 text-xs"><code class="text-xs">{{ $agendaLinksPath }}</code></p>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="splis-card p-6">
        <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">1. Final resolutions (oldsp)</h2>
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            Applies edits from an <code class="text-xs">SP_*.csv</code> export. Matches by legacy <code class="text-xs">ID</code> and updates titles, authors, categories, and dates.
        </p>
        <form method="POST" action="{{ route('admin.data-sync.resolutions') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label for="sp_csv" class="splis-label">Upload SP export CSV</label>
                <input type="file" name="sp_csv" id="sp_csv" accept=".csv,text/csv" class="splis-input mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                <p class="mt-1 text-xs text-slate-500">Optional — leave empty to use the newest <code>SP_*.csv</code> on the server.</p>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="include_lookups" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Also refresh lookup tables from server folder
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="dry_run" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Dry run (preview counts only)
            </label>
            <button type="submit" class="splis-btn-primary">Sync final resolutions</button>
        </form>
        <p class="mt-3 text-xs text-slate-500">CLI: <code>php artisan splis:import-from-csv</code></p>
    </div>

    <div class="splis-card p-6">
        <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">2. Incoming (sptrack)</h2>
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            Creates new incoming documents and <strong>updates all fields</strong> on existing rows matched by sptrack File ID. Link status and resolution links are preserved.
        </p>
        <form method="POST" action="{{ route('admin.data-sync.incoming') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label for="incoming-sptrack-csv" class="splis-label">Upload sptrack CSV</label>
                <input type="file" name="sptrack_csv" id="incoming-sptrack-csv" accept=".csv,text/csv" class="splis-input mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                <p class="mt-1 text-xs text-slate-500">Optional — when uploaded, the file is used instead of database/server CSV.</p>
            </div>
            <div>
                <label for="incoming-source" class="splis-label">Source (when no upload)</label>
                <select name="source" id="incoming-source" class="splis-input mt-1">
                    <option value="auto">Auto (CSV if present, else database)</option>
                    <option value="database">MySQL sptrack</option>
                    <option value="csv">CSV on server</option>
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="dry_run" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Dry run
            </label>
            <button type="submit" class="splis-btn-primary">Sync sptrack incoming</button>
        </form>
        <p class="mt-3 text-xs text-slate-500">CLI: <code>php artisan splis:import-incoming-from-sptrack</code></p>
    </div>

    <div class="splis-card p-6">
        <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">3. Linked resolutions (sptrack)</h2>
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            Updates final resolutions already linked via <code class="text-xs">legacy_file_id</code> with SP-side sptrack fields (title, dates, workflow, PDF URLs).
        </p>
        <form method="POST" action="{{ route('admin.data-sync.sptrack-resolutions') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label for="resolution-sptrack-csv" class="splis-label">Upload sptrack CSV</label>
                <input type="file" name="sptrack_csv" id="resolution-sptrack-csv" accept=".csv,text/csv" class="splis-input mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                <p class="mt-1 text-xs text-slate-500">Optional — when uploaded, the file is used instead of database/server CSV.</p>
            </div>
            <div>
                <label for="resolution-source" class="splis-label">Source (when no upload)</label>
                <select name="source" id="resolution-source" class="splis-input mt-1">
                    <option value="auto">Auto (CSV if present, else database)</option>
                    <option value="database">MySQL sptrack</option>
                    <option value="csv">CSV on server</option>
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="dry_run" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Dry run
            </label>
            <button type="submit" class="splis-btn-primary">Sync linked resolutions</button>
        </form>
    </div>

    <div class="splis-card p-6">
        <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">4. Agenda tracker</h2>
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            Imports or updates agenda rows matched by <code class="text-xs">tracking_no</code>. Rows without a tracking number but with sender, title, or request PDF are imported as <strong>urgent requests</strong> (matched by PDF URL or sender + title + date received). Server default: <code class="text-xs">oldsp/Agenda4.csv</code> (data + Google Drive PDF URLs). Optional separate links file: <code class="text-xs">Agenda3.csv</code>.
        </p>
        <form method="POST" action="{{ route('admin.data-sync.agenda') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label for="agenda_csv" class="splis-label">Upload agenda CSV</label>
                <input type="file" name="agenda_csv" id="agenda_csv" accept=".csv,text/csv" class="splis-input mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                <p class="mt-1 text-xs text-slate-500">Optional — leave empty to use the server path above.</p>
            </div>
            <div>
                <label for="agenda_links_csv" class="splis-label">Upload PDF links CSV</label>
                <input type="file" name="agenda_links_csv" id="agenda_links_csv" accept=".csv,text/csv" class="splis-input mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                <p class="mt-1 text-xs text-slate-500">Optional — e.g. <code>Agenda3.csv</code> with Drive URLs. Uses server links file when omitted.</p>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="dry_run" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Dry run
            </label>
            <button type="submit" class="splis-btn-primary">Sync agenda</button>
        </form>
        <p class="mt-3 text-xs text-slate-500">CLI: <code>php artisan splis:import-agenda-from-csv</code></p>
    </div>

    <div class="splis-card p-6">
        <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">5. Backfill resolution PDF paths</h2>
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            Writes <code class="text-xs">pdf_path</code> from series + resolution number only — does not copy or move files.
            Example: <code class="text-xs">resolutions/2006/2006-A-0003.pdf</code>.
            Place PDFs under <code class="text-xs">storage/app/resolutions/</code> yourself.
        </p>
        <form method="POST" action="{{ route('admin.data-sync.link-pdfs') }}" class="space-y-4">
            @csrf
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="only_missing" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Only missing (skip rows that already have pdf_path)
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="dry_run" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Dry run (preview counts only)
            </label>
            <button type="submit" class="splis-btn-primary">Backfill pdf_path</button>
        </form>
        <p class="mt-3 text-xs text-slate-500">CLI: <code>php artisan resolutions:link-pdfs --only-missing</code></p>
    </div>
</div>

@if ($recentLogs->isNotEmpty())
    <div class="mt-8 splis-card p-6">
        <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Recent sync activity</h2>
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
@endsection
