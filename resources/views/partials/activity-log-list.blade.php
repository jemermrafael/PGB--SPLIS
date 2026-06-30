@php
    $actionLabels = [
        'incoming.created' => 'Created in SPLIS',
        'incoming.updated' => 'Updated in SPLIS',
        'incoming.linked' => 'Linked to resolution',
        'incoming.imported_from_sptrack' => 'sptrack import batch',
        'resolution.created' => 'Resolution created',
        'resolution.updated' => 'Resolution updated',
        'resolution.deleted' => 'Resolution deleted',
        'resolution.published_from_incoming' => 'Published from incoming',
    ];
@endphp

<div class="splis-card mt-6">
    <div class="splis-card-header">
        <h2 class="splis-card-title">Activity</h2>
        <p class="text-sm text-slate-500">SPLIS audit trail for this record.</p>
    </div>
    <ul class="divide-y divide-slate-100 dark:divide-slate-700">
        @foreach ($activityLogs as $log)
            <li class="px-5 py-3 text-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="font-medium text-slate-800 dark:text-slate-100">
                        {{ $actionLabels[$log->action] ?? str_replace('.', ' ', ucfirst($log->action)) }}
                    </span>
                    <time class="text-xs text-slate-500" datetime="{{ $log->created_at->toIso8601String() }}">
                        {{ $log->created_at->format('M d, Y g:i A') }}
                    </time>
                </div>
                <p class="mt-0.5 text-slate-600 dark:text-slate-300">
                    {{ $log->user?->name ?? 'System' }}
                    @if (! empty($log->properties['resolution_no']))
                        · {{ $log->properties['resolution_no'] }}
                    @endif
                </p>
            </li>
        @endforeach
    </ul>
</div>
