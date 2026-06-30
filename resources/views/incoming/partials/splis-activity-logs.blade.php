@php
    use App\Support\ActivityChangeRecorder;

    $actionLabels = [
        'incoming.created' => 'Created in SPLIS',
        'incoming.updated' => 'Updated in SPLIS',
        'incoming.linked' => 'Linked to resolution',
        'resolution.published_from_incoming' => 'Published to resolution',
    ];
@endphp

@if ($splisActivityLogs->isNotEmpty())
    <div class="border-t border-slate-100 pt-5 dark:border-slate-700">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">SPLIS history</p>
        <ul class="splis-activity-timeline">
            @foreach ($splisActivityLogs as $log)
                <li class="splis-activity-timeline-item">
                    <p class="splis-activity-timeline-action">
                        {{ $actionLabels[$log->action] ?? str_replace('.', ' ', ucfirst($log->action)) }}
                    </p>
                    <p class="splis-activity-timeline-meta">
                        <time datetime="{{ $log->created_at->toIso8601String() }}">
                            {{ $log->created_at->format('M d, Y g:i A') }}
                        </time>
                        · {{ $log->user?->name ?? 'System' }}
                    </p>

                    @if (! empty($log->properties['resolution_no']))
                        <p class="splis-activity-timeline-detail">
                            Resolution {{ $log->properties['resolution_no'] }}
                        </p>
                    @endif

                    @if (! empty($log->properties['source']))
                        <p class="splis-activity-timeline-detail capitalize">
                            Source: {{ $log->properties['source'] }}
                        </p>
                    @endif

                    @if (! empty($log->properties['fields']))
                        <ul class="splis-activity-changes">
                            @foreach ($log->properties['fields'] as $field => $value)
                                <li>
                                    <span class="splis-activity-change-label">{{ ActivityChangeRecorder::incomingFieldLabel($field) }}</span>
                                    <span class="splis-activity-change-value">{{ ActivityChangeRecorder::formatValue($value) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if (! empty($log->properties['changes']))
                        <ul class="splis-activity-changes">
                            @foreach ($log->properties['changes'] as $field => $change)
                                <li>
                                    <span class="splis-activity-change-label">{{ ActivityChangeRecorder::incomingFieldLabel($field) }}</span>
                                    <span class="splis-activity-change-diff">
                                        <span class="splis-activity-change-from">{{ ActivityChangeRecorder::formatValue($change['from'] ?? null) }}</span>
                                        <span class="splis-activity-change-arrow">→</span>
                                        <span class="splis-activity-change-to">{{ ActivityChangeRecorder::formatValue($change['to'] ?? null) }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
