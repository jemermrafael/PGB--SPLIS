@extends('layouts.app')

@section('title', 'Database backups — '.config('app.name'))

@section('content')
<div class="splis-page-header">
    <div>
        <h1 class="splis-page-title">Database backups</h1>
        <p class="splis-page-subtitle">Daily MySQL dumps for disaster recovery and refreshing other environments. Keeps {{ $retentionDays }} days.</p>
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

<div class="mb-6 splis-card p-5 text-sm text-slate-600 dark:text-slate-400">
    <p class="font-medium text-slate-900 dark:text-slate-100">Schedule</p>
    <p class="mt-1">Automatic backup daily at <strong>{{ $scheduleTime }}</strong> server time (<code class="text-xs">splis:backup-database</code>).</p>
    <p class="mt-2">Storage: <code class="text-xs">{{ $directory }}</code></p>
    <p class="mt-3 text-xs">Restore locally: <code>gunzip -c splis-YYYY-MM-DD-HHMMSS.sql.gz | mysql -u USER -p splis</code></p>
</div>

<div class="splis-card overflow-hidden">
    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
        <thead class="bg-slate-50 dark:bg-slate-900/50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">File</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Created</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Size</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Download</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse ($backups as $backup)
                <tr>
                    <td class="px-4 py-3 text-sm font-mono text-slate-800 dark:text-slate-200">{{ $backup['filename'] }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $backup['created_at'] }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $backup['size_label'] }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.backups.download', $backup['filename']) }}" class="splis-btn-secondary text-sm">Download</a>
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
