@extends('layouts.app')

@section('title', $agenda->displayLabel().' — Agenda — '.config('app.name'))

@section('content')
@php
    use App\Support\AgendaPdfSlot;
@endphp
<div class="max-w-6xl">
    @php
        $agendaSubtitle = ($agenda->sender ? $agenda->sender.' · ' : '').'Version '.$agenda->current_version_no;
        $latestObPlacement = $finalObPlacements
            ->filter(fn ($placement) => $placement->legislativeSession?->session_date)
            ->sortByDesc(fn ($placement) => $placement->legislativeSession->session_date)
            ->first();
    @endphp
    <x-page-header
        class="!mb-6"
        :title="'Agenda '.$agenda->displayLabel()"
        :subtitle="$agendaSubtitle"
    >
        <x-slot:badges>
            <span class="splis-agenda-status splis-agenda-status--{{ $agenda->status }}">{{ config('agenda.statuses.'.$agenda->status, $agenda->status) }}</span>
            @if ($agenda->is_urgent_request)
                <span class="splis-badge-linked whitespace-nowrap">Urgent Request</span>
            @endif
            @if ($latestObPlacement?->legislativeSession)
                <span class="splis-badge-linked whitespace-nowrap">
                    Scheduled on {{ $latestObPlacement->legislativeSession->session_number ?: 'Order of Business' }} Order of Business {{ $latestObPlacement->legislativeSession->session_date?->format('M d, Y') }}
                </span>
            @endif
            @if ($agenda->hasIncoming())
                <a href="{{ route('incoming.show', $agenda->incomingDocument) }}" class="splis-badge-linked">Incoming linked</a>
            @endif
            @if ($agenda->resolution)
                <a href="{{ route('resolutions.show', $agenda->resolution) }}" class="splis-badge-linked whitespace-nowrap">
                    Published to Resolution No.: {{ $agenda->resolution->resolution_no }} · Series {{ $agenda->resolution->series }}
                </a>
            @endif
            @if ($agenda->ordinance)
                <a href="{{ route('ordinances.show', $agenda->ordinance) }}" class="splis-badge-linked whitespace-nowrap">
                    Published to {{ $agenda->ordinance->displayNumber() }} · Series {{ $agenda->ordinance->series_year }}
                </a>
            @endif
            @if ($agenda->appropriationOrdinance)
                <a href="{{ route('appropriation-ordinances.show', $agenda->appropriationOrdinance) }}" class="splis-badge-linked whitespace-nowrap">Published to Appropriation Ordinance</a>
            @endif
            @if ($agenda->publishedTargetLabel() && ! $agenda->resolution && ! $agenda->ordinance && ! $agenda->appropriationOrdinance)
                <span class="splis-badge-linked">Published to {{ $agenda->publishedTargetLabel() }}</span>
            @endif
        </x-slot:badges>
        <x-slot:meta>
            <div class="flex flex-wrap justify-end gap-2">
                @can('promote', $agenda)
                    @if (config('incoming.enabled', false))
                        <form method="POST" action="{{ route('agenda.promote-incoming', $agenda) }}">
                            @csrf
                            <button type="submit" class="splis-btn-primary inline-flex items-center gap-2 text-nowrap">
                                <x-icon name="inbox" class="h-4 w-4" />
                                Create Incoming
                            </button>
                        </form>
                    @endif
                @endcan
                @if ($agenda->pdfPublicUrlFor(AgendaPdfSlot::REQUEST))
                    @include('partials.pdf-modal-trigger', [
                        'url' => $agenda->pdfPublicUrlFor(AgendaPdfSlot::REQUEST),
                        'viewer' => $agenda->pdfViewerModeFor(AgendaPdfSlot::REQUEST),
                        'title' => 'Request PDF — '.$agenda->displayLabel(),
                        'label' => 'Request PDF',
                        'class' => 'splis-btn-secondary inline-flex items-center gap-2 text-nowrap',
                    ])
                @endif
                @can('update', $agenda)
                    @if ($agenda->missingPdfMirrorSlots() !== [])
                        <form method="POST" action="{{ route('agenda.mirror-pdf', $agenda) }}">
                            @csrf
                            <button type="submit" class="splis-btn-secondary inline-flex items-center gap-2 text-nowrap">
                                <x-icon name="download" class="h-4 w-4" />
                                Download from Drive
                            </button>
                        </form>
                    @endif
                @endcan
                @can('update', $agenda)
                    <a href="{{ route('agenda.edit', $agenda) }}" class="splis-btn-secondary inline-flex items-center gap-2 text-nowrap">
                        <x-icon name="edit" class="h-4 w-4" />
                        Edit
                    </a>
                @endcan
                <a href="{{ route('agenda.index') }}" class="splis-btn-ghost inline-flex items-center gap-2 text-nowrap">
                    <x-icon name="arrow-left" class="h-4 w-4" />
                    Back to list
                </a>
            </div>
        </x-slot:meta>
    </x-page-header>

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
                        'Time Received' => $agenda->time_received
                            ? \Illuminate\Support\Carbon::parse($agenda->time_received)->format('g:i A')
                            : null,
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
                    <h2 class="splis-card-title">Provincial Output</h2>
                </div>
                <dl>
                    @foreach ([
                        'Date Passed' => $agenda->date_passed?->format('M d, Y'),
                        'Date Signed by Gov.' => $agenda->date_signed_by_gov?->format('M d, Y'),
                        'Reso./Ord./AO No.' => null,
                        'Measure Type' => $agenda->effectiveMeasureType()
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
            @if ($agenda->hasIncoming() || $agenda->resolution || $agenda->ordinance || $agenda->appropriationOrdinance || $obPlacements->isNotEmpty() || auth()->user()?->can('addToOrderOfBusiness', $agenda) || auth()->user()?->can('linkOutput', $agenda) || auth()->user()?->can('removeFromOrderOfBusiness', $agenda))
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
                                    <form
                                        method="POST"
                                        action="{{ route('agenda.unlink-incoming', $agenda) }}"
                                        data-confirm-submit
                                        data-confirm-title="Unlink incoming document?"
                                        data-confirm-message="Remove the incoming link? Agenda-created incoming records will be deleted."
                                        data-confirm-label="Unlink"
                                    >
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
                                    <form
                                        method="POST"
                                        action="{{ route('agenda.unlink-resolution', $agenda) }}"
                                        data-confirm-submit
                                        data-confirm-title="Unlink resolution?"
                                        data-confirm-message="Remove the resolution link from this agenda item?"
                                        data-confirm-label="Unlink"
                                    >
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
                        @can('linkOutput', $agenda)
                            <div class="border-t border-slate-200 pt-4 dark:border-slate-700">
                                <p class="splis-detail-label">Link provincial output</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    No exact match for
                                    {{ $agenda->measureTypeLabel() }}
                                    {{ $agenda->resoDisplayLabel() ?: ($agenda->reso_ord_ao_no.' / '.$agenda->reso_ord_ao_series) }}.
                                    Choose from the list:
                                </p>
                                @if (($outputLinkCandidates ?? collect())->isEmpty())
                                    <p class="mt-2 text-sm text-slate-500">No candidates found in SPLIS for this series.</p>
                                @else
                                    <form method="POST" action="{{ route('agenda.link-output', $agenda) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <select name="output_id" class="splis-select" required onchange="document.getElementById('agenda-output-type').value = this.options[this.selectedIndex].dataset.type || ''">
                                            <option value="">Select output…</option>
                                            @foreach ($outputLinkCandidates as $candidate)
                                                <option value="{{ $candidate['id'] }}" data-type="{{ $candidate['type'] }}">
                                                    {{ $candidate['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" name="output_type" id="agenda-output-type" value="{{ $agenda->effectiveMeasureType() }}">
                                        <button type="submit" class="splis-btn-secondary w-full text-sm">Link selected output</button>
                                    </form>
                                @endif
                                @error('link_output')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endcan
                        @include('agenda.partials.ob-placements', ['agenda' => $agenda, 'placements' => $obPlacements])
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

            @if ($agenda->pdfPublicUrlFor(AgendaPdfSlot::COMMITTEE_REPORT) || $agenda->pdfPublicUrlFor(AgendaPdfSlot::RESO_ORD_AO) || $agenda->pdfPublicUrlFor(AgendaPdfSlot::JOURNAL) || $agenda->pdfPublicUrlFor(AgendaPdfSlot::MINUTES) || $agenda->isPublished())
                <div class="splis-card">
                    <div class="splis-card-header">
                        <h2 class="splis-card-title">Documents</h2>
                    </div>
                    <div class="splis-card-body flex flex-col gap-2">
                        @if ($agenda->pdfPublicUrlFor(AgendaPdfSlot::COMMITTEE_REPORT))
                            @include('partials.pdf-modal-trigger', [
                                'url' => $agenda->pdfPublicUrlFor(AgendaPdfSlot::COMMITTEE_REPORT),
                                'viewer' => $agenda->pdfViewerModeFor(AgendaPdfSlot::COMMITTEE_REPORT),
                                'title' => 'Committee Report — '.$agenda->displayLabel(),
                                'label' => 'Committee Report',
                            ])
                        @endif
                        @if ($agenda->isPublished() && $agenda->publishedTargetRoute())
                            <a href="{{ $agenda->publishedTargetRoute() }}" class="splis-btn-secondary text-sm">{{ $agenda->splisOutputButtonLabel() }}</a>
                        @elseif ($agenda->pdfPublicUrlFor(AgendaPdfSlot::RESO_ORD_AO))
                            @include('partials.pdf-modal-trigger', [
                                'url' => $agenda->pdfPublicUrlFor(AgendaPdfSlot::RESO_ORD_AO),
                                'viewer' => $agenda->pdfViewerModeFor(AgendaPdfSlot::RESO_ORD_AO),
                                'title' => $agenda->legacyOutputPdfButtonLabel().' — '.$agenda->displayLabel(),
                                'label' => $agenda->legacyOutputPdfButtonLabel(),
                            ])
                        @endif
                        @if ($agenda->pdfPublicUrlFor(AgendaPdfSlot::JOURNAL))
                            @include('partials.pdf-modal-trigger', [
                                'url' => $agenda->pdfPublicUrlFor(AgendaPdfSlot::JOURNAL),
                                'viewer' => $agenda->pdfViewerModeFor(AgendaPdfSlot::JOURNAL),
                                'title' => 'Journal of Proceedings — '.$agenda->displayLabel(),
                                'label' => 'Journal of Proceedings',
                                'class' => 'splis-btn-secondary text-sm inline-flex items-center justify-center gap-2',
                            ])
                        @endif
                        @if ($agenda->pdfPublicUrlFor(AgendaPdfSlot::MINUTES))
                            @include('partials.pdf-modal-trigger', [
                                'url' => $agenda->pdfPublicUrlFor(AgendaPdfSlot::MINUTES),
                                'viewer' => $agenda->pdfViewerModeFor(AgendaPdfSlot::MINUTES),
                                'title' => 'Minutes of Session — '.$agenda->displayLabel(),
                                'label' => 'Minutes of Session',
                                'class' => 'splis-btn-secondary text-sm inline-flex items-center justify-center gap-2',
                            ])
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    @include('agenda.partials.version-history', ['agenda' => $agenda])

    @include('agenda.partials.splis-activity-logs', [
        'splisActivityLogs' => $splisActivityLogs ?? collect(),
        'obPlacementCount' => $obPlacementCount ?? 0,
    ])

    @include('partials.detail-prev-next', [
        'previous' => $previousAgenda ?? null,
        'next' => $nextAgenda ?? null,
        'previousUrl' => ($previousAgenda ?? null) ? route('agenda.show', $previousAgenda) : null,
        'nextUrl' => ($nextAgenda ?? null) ? route('agenda.show', $nextAgenda) : null,
        'previousLabel' => isset($previousAgenda) ? $previousAgenda->displayLabel() : null,
        'nextLabel' => isset($nextAgenda) ? $nextAgenda->displayLabel() : null,
        'label' => 'Agenda navigation',
    ])
</div>
@endsection
