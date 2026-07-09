@php
    $actionLabels = [
        'agenda.created' => 'Agenda created',
        'agenda.published' => 'Agenda published',
        'agenda.added_to_ob' => 'Added to Order of Business',
        'agenda.removed_from_ob' => 'Removed from Order of Business',
        'agenda.ob_relocated' => 'Moved in Order of Business',
    ];
    $obActions = array_keys($actionLabels);
@endphp

@if ($splisActivityLogs->isNotEmpty())
    <aside class="splis-card mt-6">
        <div class="splis-card-header">
            <div>
                <h2 class="splis-card-title">History</h2>
                @if (($obPlacementCount ?? 0) > 0)
                    <p class="splis-card-subtitle">
                        Added to Order of Business {{ $obPlacementCount }} {{ $obPlacementCount === 1 ? 'time' : 'times' }}
                    </p>
                @endif
            </div>
        </div>
        <div class="splis-card-body">
            <ul class="splis-activity-timeline">
                @foreach ($splisActivityLogs as $log)
                    <li class="splis-activity-timeline-item">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                        <p class="splis-activity-timeline-action">
                            {{ $actionLabels[$log->action] ?? str_replace('.', ' ', ucfirst($log->action)) }}
                            @if (in_array($log->action, $obActions, true) && ! empty($log->properties['agenda_version_no']))
                                <span class="font-normal text-slate-500">· version {{ $log->properties['agenda_version_no'] }}</span>
                            @endif
                        </p>
                        <p class="splis-activity-timeline-meta">
                            <time datetime="{{ $log->created_at->toIso8601String() }}">
                                {{ $log->created_at->format('M d, Y g:i A') }}
                            </time>
                            · {{ $log->user?->name ?? 'System' }}
                            @if ($log->user?->role)
                                <span class="text-slate-400">({{ $log->user->role->label() }})</span>
                            @endif
                        </p>

                        @if (! empty($log->properties['target']))
                            <p class="splis-activity-timeline-detail">
                                Published to: {{ $log->properties['target'] }}
                                @if (! empty($log->properties['output_no']))
                                    · {{ $log->properties['output_no'] }}
                                @endif
                            </p>
                        @endif

                        @if (! empty($log->properties['session_title']))
                            <p class="splis-activity-timeline-detail">
                                Session: {{ $log->properties['session_title'] }}
                            </p>
                        @endif

                        @if (! empty($log->properties['section_label']))
                            <p class="splis-activity-timeline-detail">
                                Section: {{ $log->properties['section_label'] }}
                            </p>
                        @endif

                        @if (! empty($log->properties['from_section_label']) && ! empty($log->properties['to_section_label']))
                            <p class="splis-activity-timeline-detail">
                                {{ $log->properties['from_section_label'] }} → {{ $log->properties['to_section_label'] }}
                            </p>
                        @endif

                        @if (! empty($log->properties['source']))
                            <p class="splis-activity-timeline-detail capitalize">
                                {{ $log->properties['source'] === 'automatic' ? 'Automatic' : 'Manual' }}
                            </p>
                        @endif
                            </div>
                            @include('partials.activity-log-delete', ['log' => $log])
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </aside>
@endif
