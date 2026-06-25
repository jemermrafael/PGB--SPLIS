@extends('layouts.app')

@section('title', 'SP Track Migration — '.config('app.name'))

@section('content')
<div class="splis-page-header">
    <div>
        <h1 class="splis-page-title">SP Track Migration</h1>
        <p class="splis-page-subtitle">Analyze sptrack workflow data, review matches, and enrich SPLIS resolutions.</p>
    </div>
    <a href="{{ route('admin.sptrack.queue') }}" class="splis-btn-primary">Review queue</a>
</div>

<div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="splis-stat">
        <p class="splis-stat-label">Pending review</p>
        <p class="splis-stat-value">{{ number_format($counts['pending']) }}</p>
    </div>
    <div class="splis-stat">
        <p class="splis-stat-label">High confidence</p>
        <p class="splis-stat-value">{{ number_format($counts['high']) }}</p>
    </div>
    <div class="splis-stat">
        <p class="splis-stat-label">Ready to apply</p>
        <p class="splis-stat-value">{{ number_format($counts['approved']) }}</p>
    </div>
    <div class="splis-stat">
        <p class="splis-stat-label">Applied</p>
        <p class="splis-stat-value">{{ number_format($counts['applied']) }}</p>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="splis-card p-6">
        <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">1. Analyze sptrack</h2>
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            Source: <strong>{{ $source }}</strong>.
            @if ($batchId)
                Latest batch: <code class="text-xs">{{ $batchId }}</code>
            @endif
        </p>
        <form method="POST" action="{{ route('admin.sptrack.analyze') }}" class="space-y-4">
            @csrf
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="keep_queue" value="1" class="rounded border-slate-300">
                Keep existing pending/approved queue rows
            </label>
            <button type="submit" class="splis-btn-primary">Run analysis</button>
        </form>
        <p class="mt-3 text-xs text-slate-500">CLI: <code>php artisan splis:analyze-sptrack</code></p>
    </div>

    <div class="splis-card p-6">
        <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">2. Approve &amp; apply</h2>
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            Approve high-confidence matches in bulk, review uncertain rows, then apply approved items to SPLIS.
        </p>
        <div class="flex flex-wrap gap-3">
            <form method="POST" action="{{ route('admin.sptrack.approve-high') }}">
                @csrf
                <button type="submit" class="splis-btn-primary" @disabled($counts['high'] === 0)>
                    Approve all high ({{ $counts['high'] }})
                </button>
            </form>
            <form method="POST" action="{{ route('admin.sptrack.approve-create') }}">
                @csrf
                <button type="submit" class="splis-btn-secondary" @disabled($counts['create'] === 0)>
                    Approve all create-new ({{ $counts['create'] }})
                </button>
            </form>
            <form method="POST" action="{{ route('admin.sptrack.apply') }}" onsubmit="return confirm('Apply all approved queue rows to SPLIS?');">
                @csrf
                <button type="submit" class="splis-btn-secondary" @disabled($counts['approved'] === 0)>
                    Apply approved ({{ $counts['approved'] }})
                </button>
            </form>
        </div>
        <p class="mt-3 text-xs text-slate-500">CLI: <code>php artisan splis:apply-sptrack-queue</code></p>
    </div>
</div>

<div class="mt-6 splis-card p-6">
    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Queue breakdown</h2>
    <div class="flex flex-wrap gap-3 text-sm">
        <a href="{{ route('admin.sptrack.queue', ['tab' => 'high']) }}" class="splis-btn-secondary">High confidence ({{ $counts['high'] }})</a>
        <a href="{{ route('admin.sptrack.queue', ['tab' => 'review']) }}" class="splis-btn-secondary">Needs review ({{ $counts['review'] }})</a>
        <a href="{{ route('admin.sptrack.queue', ['tab' => 'create']) }}" class="splis-btn-secondary">Create new ({{ $counts['create'] }})</a>
        <a href="{{ route('admin.sptrack.queue', ['tab' => 'approved']) }}" class="splis-btn-secondary">Approved ({{ $counts['approved'] }})</a>
        <a href="{{ route('admin.sptrack.queue', ['tab' => 'applied']) }}" class="splis-btn-secondary">Applied ({{ $counts['applied'] }})</a>
    </div>
</div>
@endsection
