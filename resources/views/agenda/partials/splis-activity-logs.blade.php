@php
    $actionLabels = [
        'agenda.created' => 'Agenda created',
        'agenda.published' => 'Agenda published',
        'agenda.added_to_ob' => 'Added to Order of Business',
        'agenda.removed_from_ob' => 'Removed from Order of Business',
        'agenda.ob_relocated' => 'Moved in Order of Business',
    ];
    $obActions = array_keys($actionLabels);
    $historyCount = $splisActivityLogs->count();
    $historySubtitle = ($obPlacementCount ?? 0) > 0
        ? 'Added to Order of Business '.$obPlacementCount.' '.(($obPlacementCount === 1) ? 'time' : 'times')
        : null;
@endphp

@if ($splisActivityLogs->isNotEmpty())
    <x-history-accordion
        title="History"
        :subtitle="$historySubtitle"
        :count="$historyCount"
        :aside="true"
        :open="true"
    >
        <div class="splis-card-body">
            <ul class="splis-activity-timeline">
                @foreach ($splisActivityLogs as $log)
                    <li class="splis-activity-timeline-item">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                        <p class="splis-activity-timeline-action">
                            @if ($log->action === 'agenda.ob_relocated' && ! empty($log->properties['to_section_label']))
                                Moved to {{ $log->properties['to_section_label'] }}
                            @elseif ($log->action === 'agenda.added_to_ob' && ! empty($log->properties['section_labels']) && count($log->properties['section_labels']) > 1)
                                Added to {{ implode(' and ', $log->properties['section_labels']) }}
                            @elseif ($log->action === 'agenda.added_to_ob' && ! empty($log->properties['section_label']))
                                Added to {{ $log->properties['section_label'] }}
                            @else
                                {{ $actionLabels[$log->action] ?? str_replace('.', ' ', ucfirst($log->action)) }}
                            @endif
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

                        @if ($log->action !== 'agenda.added_to_ob' && ! empty($log->properties['section_label']))
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
    </x-history-accordion>
@endif
