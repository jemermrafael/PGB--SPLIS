@extends('layouts.app')

@section('title', $agenda->displayLabel().' — Agenda — '.config('app.name'))

@section('content')
<div class="max-w-6xl">
    <div class="splis-page-header !mb-6">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <span class="splis-agenda-status splis-agenda-status--{{ $agenda->status }}">{{ config('agenda.statuses.'.$agenda->status, $agenda->status) }}</span>
                @if ($agenda->hasIncoming())
                    <a href="{{ route('incoming.show', $agenda->incomingDocument) }}" class="splis-badge-linked">Incoming linked</a>
                @endif
                @if ($agenda->resolution)
                    <a href="{{ route('resolutions.show', $agenda->resolution) }}" class="splis-badge-linked">Resolution linked</a>
                @endif
                @if ($agenda->ordinance)
                    <a href="{{ route('ordinances.show', $agenda->ordinance) }}" class="splis-badge-linked">Published to Ordinance</a>
                @endif
                @if ($agenda->appropriationOrdinance)
                    <a href="{{ route('appropriation-ordinances.show', $agenda->appropriationOrdinance) }}" class="splis-badge-linked">Published to Appropriation Ordinance</a>
                @endif
                @if ($agenda->publishedTargetLabel() && ! $agenda->resolution && ! $agenda->ordinance && ! $agenda->appropriationOrdinance)
                    <span class="splis-badge-linked">Published to {{ $agenda->publishedTargetLabel() }}</span>
                @endif
            </div>
            <h1 class="splis-page-title">Agenda {{ $agenda->displayLabel() }}</h1>
            <p class="splis-page-subtitle">
                @if ($agenda->sender){{ $agenda->sender }} · @endif
                Version {{ $agenda->current_version_no }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('promote', $agenda)
                @if (config('incoming.enabled', false))
                <form method="POST" action="{{ route('agenda.promote-incoming', $agenda) }}">
                    @csrf
                    <button type="submit" class="splis-btn-primary">Create Incoming</button>
                </form>
                @endif
            @endcan
            @can('update', $agenda)
                <a href="{{ route('agenda.edit', $agenda) }}" class="splis-btn-secondary">Edit</a>
            @endcan
            <a href="{{ route('agenda.index') }}" class="splis-btn-secondary">Back to list</a>
        </div>
    </div>

    @if (session('status'))
        <div class="splis-alert-success mb-6">{{ session('status') }}</div>
    @endif

    @if ($errors->has('promote') || $errors->has('unlink') || $errors->has('version'))
        <div class="splis-alert-error mb-6">{{ $errors->first('promote') ?: $errors->first('unlink') ?: $errors->first('version') }}</div>
    @endif

    <div class="splis-card splis-card-body mb-6">
        @include('agenda.partials.workflow-timeline', ['steps' => $agenda->workflowSteps()])
    </div>

    <div class="splis-detail-with-sidebar">
        <div class="min-w-0 space-y-6">
            <div class="splis-card">
                <div class="splis-card-header">
                    <h2 class="splis-card-title">Intake</h2>
                </div>
                <dl>
                    @if ($agenda->title)
                        <div class="splis-detail-row">
                            <dt class="splis-detail-label">Title</dt>
                            <dd class="splis-detail-value">{{ $agenda->title }}</dd>
                        </div>
                    @endif
                    @foreach ([
                        'Date Received' => $agenda->date_received?->format('M d, Y'),
                        'Time Received' => $agenda->time_received ? \Illuminate\Support\Str::of($agenda->time_received)->substr(0, 5) : null,
                        'Prescribed Days' => $agenda->prescribed_days,
                        'Sender' => $agenda->sender,
                    ] as $label => $value)
                        @if ($value !== null && $value !== '')
                            <div class="splis-detail-row">
                                <dt class="splis-detail-label">{{ $label }}</dt>
                                <dd class="splis-detail-value">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>

            <div class="splis-card">
                <div class="splis-card-header">
                    <h2 class="splis-card-title">Committee</h2>
                </div>
                <dl>
                    @foreach ([
                        'Committee Referred' => $agenda->committee_referred,
                        'Date of Referral' => $agenda->date_of_referral?->format('M d, Y'),
                        'Date of Committee Meeting' => $agenda->date_of_committee_meeting?->format('M d, Y'),
                        'Committee Meeting Minutes' => $agenda->committee_meeting_minutes,
                        'Outcome' => $agenda->outcome,
                    ] as $label => $value)
                        @if ($value !== null && $value !== '')
                            <div class="splis-detail-row">
                                <dt class="splis-detail-label">{{ $label }}</dt>
                                <dd class="splis-detail-value">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>

            <div class="splis-card">
                <div class="splis-card-header">
                    <h2 class="splis-card-title">Provincial output</h2>
                </div>
                <dl>
                    @foreach ([
                        'Date Passed' => $agenda->date_passed?->format('M d, Y'),
                        'Date Signed by Gov.' => $agenda->date_signed_by_gov?->format('M d, Y'),
                        'Reso./Ord./AO No.' => null,
                        'Measure Type' => $agenda->reso_ord_ao_type
                            ? $agenda->measureTypeLabel()
                            : (($agenda->reso_ord_ao_no || $agenda->reso_ord_ao_url) ? 'Not specified (legacy)' : null),
                        'Resolution Title' => $agenda->resolution_title,
                        'Remarks' => $agenda->remarks,
                    ] as $label => $value)
                        @if ($label === 'Reso./Ord./AO No.' && $agenda->resoDisplayLabel())
                            <div class="splis-detail-row">
                                <dt class="splis-detail-label">{{ $label }}</dt>
                                <dd class="splis-detail-value">
                                    @if ($agenda->publishedTargetRoute())
                                        <a href="{{ $agenda->publishedTargetRoute() }}" class="splis-link font-medium">{{ $agenda->resoDisplayLabel() }}</a>
                                        <span class="ml-2 text-xs text-slate-500">({{ $agenda->publishedTargetLabel() }})</span>
                                    @else
                                        {{ $agenda->resoDisplayLabel() }}
                                    @endif
                                </dd>
                            </div>
                        @elseif ($value !== null && $value !== '')
                            <div class="splis-detail-row">
                                <dt class="splis-detail-label">{{ $label }}</dt>
                                <dd class="splis-detail-value">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>
        </div>

        <div class="splis-detail-sidebar-column">
            @if ($agenda->hasIncoming() || $agenda->resolution || $agenda->ordinance || $agenda->appropriationOrdinance || $agenda->obPlacements->isNotEmpty() || auth()->user()?->can('addToOrderOfBusiness', $agenda))
                <aside class="splis-card">
                    <div class="splis-card-header">
                        <h2 class="splis-card-title">Connections</h2>
                    </div>
                    <div class="splis-card-body space-y-4">
                        @if ($agenda->hasIncoming() && $agenda->incomingDocument)
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="splis-detail-label">Incoming</p>
                                    <a href="{{ route('incoming.show', $agenda->incomingDocument) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-200">
                                        {{ $agenda->incomingDocument->displayLabel() }}
                                    </a>
                                </div>
                                @can('unlinkIncoming', $agenda)
                                    <form method="POST" action="{{ route('agenda.unlink-incoming', $agenda) }}" onsubmit="return confirm('Remove the incoming link? Agenda-created incoming records will be deleted.');">
                                        @csrf
                                        <button type="submit" class="splis-btn-ghost text-sm text-red-600 hover:text-red-700">Unlink</button>
                                    </form>
                                @endcan
                            </div>
                        @endif
                        @if ($agenda->resolution)
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="splis-detail-label">Resolution</p>
                                    <a href="{{ route('resolutions.show', $agenda->resolution) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-200">
                                        {{ $agenda->resolution->resolution_no }} / {{ $agenda->resolution->series }}
                                    </a>
                                </div>
                                @can('unlinkResolution', $agenda)
                                    <form method="POST" action="{{ route('agenda.unlink-resolution', $agenda) }}" onsubmit="return confirm('Remove the resolution link from this agenda item?');">
                                        @csrf
                                        <button type="submit" class="splis-btn-ghost text-sm text-red-600 hover:text-red-700">Unlink</button>
                                    </form>
                                @endcan
                            </div>
                        @endif
                        @if ($agenda->ordinance)
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="splis-detail-label">Ordinance</p>
                                    <a href="{{ route('ordinances.show', $agenda->ordinance) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-200">
                                        {{ $agenda->ordinance->displayNumber() }} ({{ $agenda->ordinance->series_year }})
                                    </a>
                                </div>
                            </div>
                        @endif
                        @if ($agenda->appropriationOrdinance)
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="splis-detail-label">Appropriation Ordinance</p>
                                    <a href="{{ route('appropriation-ordinances.show', $agenda->appropriationOrdinance) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-200">
                                        {{ $agenda->appropriationOrdinance->displayNumber() }} ({{ $agenda->appropriationOrdinance->series_year }})
                                    </a>
                                </div>
                            </div>
                        @endif
                        @include('agenda.partials.ob-placements', ['agenda' => $agenda])
                        @can('addToOrderOfBusiness', $agenda)
                            <div class="border-t border-slate-200 pt-4 dark:border-slate-700">
                                <p class="splis-detail-label">Add to Order of Business</p>
                                @if ($obSessions->isEmpty())
                                    <p class="mt-2 text-sm text-slate-500">
                                        No sessions yet.
                                        <a href="{{ route('ob.sessions.create') }}" class="splis-link">Create a session</a>
                                        to add this agenda item.
                                    </p>
                                @else
                                    <form method="POST" action="{{ route('agenda.add-to-order-of-business', $agenda) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <select name="legislative_session_id" class="splis-select" required>
                                            <option value="">Select session…</option>
                                            @foreach ($obSessions as $obSession)
                                                <option value="{{ $obSession->id }}">{{ $obSession->displayTitle() }}</option>
                                            @endforeach
                                        </select>
                                        <select name="agenda_section" class="splis-select">
                                            @foreach (config('order_of_business.agenda_sections', []) as $value => $label)
                                                <option value="{{ $value }}" @selected($value === 'unassigned_regular')>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="splis-btn-secondary w-full text-sm">Add to OB document</button>
                                    </form>
                                    <p class="mt-2 text-xs text-slate-500">
                                        Or <a href="{{ route('ob.sessions.create') }}" class="splis-link">create a new session</a> and add this item in the OB Maker.
                                    </p>
                                @endif
                            </div>
                        @endcan
                    </div>
                </aside>
            @endif

            <aside class="splis-card splis-agenda-tracking-card">
                <div class="splis-card-header">
                    <h2 class="splis-card-title">Tracking</h2>
                </div>
                <div class="splis-card-body space-y-5">
                    <div>
                        <p class="splis-detail-label">Status</p>
                        <p class="mt-1">
                            <span class="splis-agenda-status splis-agenda-status--{{ $agenda->status }}">
                                {{ config('agenda.statuses.'.$agenda->status, $agenda->status) }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <p class="splis-detail-label">Days left</p>
                        <p class="mt-1">
                            <span class="splis-agenda-days splis-agenda-days--{{ $agenda->daysLeftTone() }} splis-agenda-days--lg">
                                {{ $agenda->days_left_label ?? '—' }}
                            </span>
                        </p>
                    </div>
                    @if ($agenda->due_date)
                        <div>
                            <p class="splis-detail-label">Due date</p>
                            <p class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ $agenda->due_date->format('M d, Y') }}</p>
                        </div>
                    @endif
                    @if ($agenda->prescribed_days !== null)
                        <div>
                            <p class="splis-detail-label">Prescribed days</p>
                            <p class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ $agenda->prescribed_days }}</p>
                        </div>
                    @endif
                    @if ($progress = $agenda->deadlineProgressPercent())
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs text-slate-500">
                                <span>Deadline progress</span>
                                <span>{{ $progress }}%</span>
                            </div>
                            <div class="splis-agenda-progress">
                                <div class="splis-agenda-progress-bar splis-agenda-progress-bar--{{ $agenda->daysLeftTone() }}" style="width: {{ $progress }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>
            </aside>

            @if ($agenda->request_pdf_url || $agenda->committee_report_url || $agenda->reso_ord_ao_url || $agenda->journal_url || $agenda->minutes_url || $agenda->isPublished())
                <div class="splis-card">
                    <div class="splis-card-header">
                        <h2 class="splis-card-title">Documents</h2>
                    </div>
                    <div class="splis-card-body flex flex-col gap-2">
                        @if ($agenda->request_pdf_url)
                            <a href="{{ $agenda->request_pdf_url }}" target="_blank" rel="noopener" class="splis-btn-secondary text-sm">Request PDF</a>
                        @endif
                        @if ($agenda->committee_report_url)
                            <a href="{{ $agenda->committee_report_url }}" target="_blank" rel="noopener" class="splis-btn-secondary text-sm">Committee Report</a>
                        @endif
                        @if ($agenda->isPublished() && $agenda->publishedTargetRoute())
                            <a href="{{ $agenda->publishedTargetRoute() }}" class="splis-btn-secondary text-sm">{{ $agenda->splisOutputButtonLabel() }}</a>
                        @elseif ($agenda->reso_ord_ao_url)
                            <a href="{{ $agenda->reso_ord_ao_url }}" target="_blank" rel="noopener" class="splis-btn-secondary text-sm">{{ $agenda->legacyOutputPdfButtonLabel() }}</a>
                        @endif
                        @if ($agenda->journal_url)
                            <a href="{{ $agenda->journal_url }}" target="_blank" rel="noopener" class="splis-btn-secondary text-sm">Journal of Proceedings</a>
                        @endif
                        @if ($agenda->minutes_url)
                            <a href="{{ $agenda->minutes_url }}" target="_blank" rel="noopener" class="splis-btn-secondary text-sm">Minutes of Session</a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    @include('agenda.partials.version-history', ['agenda' => $agenda])
</div>
@endsection
