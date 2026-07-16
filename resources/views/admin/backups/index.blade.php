@extends('layouts.app')

@section('title', 'Database backups — '.config('app.name'))

@section('content')
<div class="splis-page-header">
    <div>
        <h1 class="splis-page-title">Database Backups</h1>
        <p class="splis-page-subtitle">Daily MySQL dumps for disaster recovery and refreshing other environments.</p>
    </div>
    <form method="POST" action="{{ route('admin.backups.store') }}">
        @csrf
        <button type="submit" class="splis-btn-primary">Backup now</button>
    </form>
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

<div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="splis-card p-6">
        <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Schedule</h2>
        <form method="POST" action="{{ route('admin.backups.settings') }}" class="space-y-4">
            @csrf
            <div>
                <label for="schedule_time" class="splis-label">Daily backup time</label>
                <input type="time" name="schedule_time" id="schedule_time" value="{{ old('schedule_time', $scheduleTime) }}" class="splis-input mt-1" required>
                <p class="mt-1 text-xs text-slate-500">App timezone (Asia/Manila). Next run uses this time when the scheduler is active.</p>
            </div>
            <div>
                <label for="retention_days" class="splis-label">Keep backups (days)</label>
                <input type="number" name="retention_days" id="retention_days" min="1" max="90" value="{{ old('retention_days', $retentionDays) }}" class="splis-input mt-1 w-32" required>
            </div>
            <button type="submit" class="splis-btn-secondary">Save settings</button>
        </form>
        <p class="mt-4 text-xs text-slate-500">Storage: <code>{{ $directory }}</code></p>
    </div>

    <div class="splis-card p-6">
        <h2 class="mb-2 text-lg font-semibold text-red-700 dark:text-red-400">Restore database</h2>
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            <strong>Warning:</strong> Restore replaces the entire <code class="text-xs">splis</code> database. Type <code class="text-xs">RESTORE</code> to confirm.
        </p>
        <form method="POST" action="{{ route('admin.backups.restore-upload') }}" enctype="multipart/form-data" class="mb-6 space-y-4">
            @csrf
            <div>
                <label for="backup_file" class="splis-label">Upload backup (.sql.gz)</label>
                <input type="file" name="backup_file" id="backup_file" accept=".gz,application/gzip" class="splis-input mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700" required>
            </div>
            <div>
                <label for="confirm_restore_upload" class="splis-label">Confirmation</label>
                <input type="text" name="confirm_restore" id="confirm_restore_upload" placeholder="RESTORE" class="splis-input mt-1" autocomplete="off" required>
            </div>
            <button type="submit" class="splis-btn-secondary border-red-300 text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300">Restore from upload</button>
        </form>
    </div>
</div>

<div class="splis-card overflow-hidden">
    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
        <thead class="bg-slate-50 dark:bg-slate-900/50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">File</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Created</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Size</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse ($backups as $backup)
                <tr>
                    <td class="px-4 py-3 text-sm font-mono text-slate-800 dark:text-slate-200">{{ $backup['filename'] }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $backup['created_at'] }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $backup['size_label'] }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <a href="{{ route('admin.backups.download', $backup['filename']) }}" class="splis-btn-secondary text-sm">Download</a>
                            <form method="POST" action="{{ route('admin.backups.restore') }}" class="inline-flex items-center gap-2" onsubmit="return confirm('Restore {{ $backup['filename'] }}? This replaces the entire database.');">
                                @csrf
                                <input type="hidden" name="filename" value="{{ $backup['filename'] }}">
                                <input type="hidden" name="confirm_restore" value="RESTORE">
                                <button type="submit" class="splis-btn-secondary text-sm text-red-700 dark:text-red-300">Restore</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No backups yet. Click <strong>Backup now</strong> or wait for the daily job.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
