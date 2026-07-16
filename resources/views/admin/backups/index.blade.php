@extends('layouts.app')

@section('title', 'Database backups — '.config('app.name'))

@section('content')
<div class="splis-page-header">
    <div>
        <h1 class="splis-page-title">Database Backups</h1>
        <p class="splis-page-subtitle">Daily MySQL dumps for disaster recovery and refreshing other environments.</p>
    </div>
    <div class="splis-page-header-actions">
        <form method="POST" action="{{ route('admin.backups.store') }}">
            @csrf
            <button type="submit" class="splis-btn-primary inline-flex items-center gap-2">
                <x-icon name="database" class="h-4 w-4" />
                Backup now
            </button>
        </form>
    </div>
</div>

<p class="splis-admin-section-title">Safe operations</p>
<div class="mb-8 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="splis-card p-6">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Schedule</h2>
            <x-risk-badge level="safe" label="Safe" />
        </div>
        <form method="POST" action="{{ route('admin.backups.settings') }}" class="space-y-4">
            @csrf
            <div>
                <label for="schedule_time" class="splis-label">Daily backup time</label>
                <input type="time" name="schedule_time" id="schedule_time" value="{{ old('schedule_time', $scheduleTime) }}" class="splis-input mt-1" required>
                <p class="mt-1 text-xs text-slate-500">App timezone (Asia/Manila). Requires the scheduler to be running.</p>
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
        <div class="mb-2 flex flex-wrap items-center gap-2">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">How to use backups</h2>
            <x-risk-badge level="safe" label="Guide" />
        </div>
        <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-600 dark:text-slate-400">
            <li>Click <strong>Backup now</strong> before risky changes or restores.</li>
            <li>Download a <code class="text-xs">.sql.gz</code> file to keep an off-server copy.</li>
            <li>Restore only when you intend to replace the entire live database.</li>
        </ol>
    </div>
</div>

<p class="splis-admin-section-title">Available backups</p>
<div class="mb-10 splis-card overflow-hidden">
    <div class="splis-table-wrap border-0 shadow-none" data-drag-scroll>
        <table class="splis-table">
            <thead>
                <tr>
                    <th>File</th>
                    <th class="hidden sm:table-cell">Created</th>
                    <th>Size</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($backups as $backup)
                    <tr>
                        <td class="font-mono text-xs sm:text-sm">{{ $backup['filename'] }}</td>
                        <td class="hidden whitespace-nowrap sm:table-cell">{{ $backup['created_at'] }}</td>
                        <td class="whitespace-nowrap">{{ $backup['size_label'] }}</td>
                        <td>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <a href="{{ route('admin.backups.download', $backup['filename']) }}" class="splis-btn-secondary text-sm">Download</a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.backups.restore') }}"
                                    class="inline-flex"
                                    onsubmit="return confirm('Restore {{ $backup['filename'] }}?\n\nThis REPLACES the entire database. Type OK only if you are sure.');"
                                >
                                    @csrf
                                    <input type="hidden" name="filename" value="{{ $backup['filename'] }}">
                                    <input type="hidden" name="confirm_restore" value="RESTORE">
                                    <button type="submit" class="splis-btn-danger text-sm">Restore</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-8 text-center text-sm text-slate-500">No backups yet. Click <strong>Backup now</strong> or wait for the daily job.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<p class="splis-admin-section-title">Danger zone</p>
<div class="splis-danger-zone">
    <div class="mb-2 flex flex-wrap items-center gap-2">
        <h2 class="splis-danger-zone-title">Restore from upload</h2>
        <x-risk-badge level="danger" label="Destructive" />
    </div>
    <p class="mb-4 text-sm text-red-900/80 dark:text-red-200/90">
        This replaces the entire <code class="text-xs">splis</code> database. Take a fresh backup first. Type <code class="text-xs">RESTORE</code> to confirm.
    </p>
    <form method="POST" action="{{ route('admin.backups.restore-upload') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div>
            <label for="backup_file" class="splis-label">Upload backup (.sql.gz)</label>
            <input type="file" name="backup_file" id="backup_file" accept=".gz,application/gzip" class="splis-input mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-red-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-red-800" required>
        </div>
        <div>
            <label for="confirm_restore_upload" class="splis-label">Confirmation</label>
            <input type="text" name="confirm_restore" id="confirm_restore_upload" placeholder="Type RESTORE" class="splis-input mt-1" autocomplete="off" required>
        </div>
        <button type="submit" class="splis-btn-danger">Restore from upload</button>
    </form>
</div>
@endsection
