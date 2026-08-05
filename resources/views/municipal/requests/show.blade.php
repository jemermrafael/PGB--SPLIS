@extends('layouts.app')

@section('title', $agenda->displayLabel().' — My Request — '.config('app.name'))

@section('content')
<div class="max-w-6xl">
    <div class="splis-page-header !mb-6">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <span class="splis-agenda-status splis-agenda-status--{{ $agenda->status }}">{{ config('agenda.statuses.'.$agenda->status, $agenda->status) }}</span>
                @if ($agenda->is_urgent_request)
                    <span class="splis-badge-linked whitespace-nowrap">Urgent Request</span>
                @endif
                @if ($finalObPlacements->isNotEmpty())
                    @php
                        $latestObPlacement = $finalObPlacements
                            ->filter(fn ($placement) => $placement->legislativeSession?->session_date)
                            ->sortByDesc(fn ($placement) => $placement->legislativeSession->session_date)
                            ->first();
                        $latestObSession = $latestObPlacement?->legislativeSession;
                        $latestObSessionEnded = $latestObSession !== null && (
                            in_array($latestObSession->status, ['completed', 'cancelled'], true)
                            || $latestObSession->isPastSessionDate()
                        );
                    @endphp
                    @if ($latestObSession && ! $latestObSessionEnded)
                        <span class="splis-badge-linked whitespace-nowrap">
                            Scheduled on {{ $latestObSession->session_number ?: 'Order of Business' }} Order of Business {{ $latestObSession->session_date?->format('M d, Y') }}
                        </span>
                    @endif
                @endif
                @if ($agenda->resolution)
                    <a href="{{ route('resolutions.show', $agenda->resolution) }}" class="splis-badge-linked whitespace-nowrap">
                        {{ $agenda->outputConnectionLabel() }} Resolution No.: {{ $agenda->resolution->resolution_no }} · Series {{ $agenda->resolution->series }}
                    </a>
                @endif
                @if ($agenda->ordinance)
                    <a href="{{ route('ordinances.show', $agenda->ordinance) }}" class="splis-badge-linked whitespace-nowrap">
                        {{ $agenda->outputConnectionLabel() }} {{ $agenda->ordinance->displayNumber() }} · Series {{ $agenda->ordinance->series_year }}
                    </a>
                @endif
                @if ($agenda->appropriationOrdinance)
                    <a href="{{ route('appropriation-ordinances.show', $agenda->appropriationOrdinance) }}" class="splis-badge-linked whitespace-nowrap">{{ $agenda->outputConnectionLabel() }} Appropriation Ordinance</a>
                @endif
            </div>
            <h1 class="splis-page-title">
                <span class="block leading-tight">Request {{ $agenda->displayLabel() }}</span>
                @if ($agenda->listYearLabel())
                    <span class="mt-1 block text-base font-normal leading-tight text-slate-500 dark:text-slate-400">{{ $agenda->listYearLabel() }}</span>
                @endif
            </h1>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($agenda->request_pdf_url)
                @include('partials.pdf-modal-trigger', [
                    'url' => $agenda->request_pdf_url,
                    'title' => 'Request PDF — '.$agenda->displayLabel(),
                    'label' => 'Request PDF',
                    'class' => 'splis-btn-secondary inline-flex items-center gap-2',
                ])
            @endif
            <a href="{{ route('municipal.requests.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Back to requests
            </a>
        </div>
    </div>

    <div class="splis-card splis-card-body mb-6">
        @include('agenda.partials.workflow-timeline', ['steps' => $agenda->workflowSteps()])
    </div>

    <div class="splis-detail-with-sidebar">
        <div class="min-w-0 space-y-6">
            <div class="splis-card overflow-hidden">
                <div class="splis-card-header splis-card-header--emphasis">
                    <h2 class="splis-card-title">Request details</h2>
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

            <div class="splis-card overflow-hidden">
                <div class="splis-card-header splis-card-header--emphasis">
                    <h2 class="splis-card-title">Committee action</h2>
                </div>
                <dl>
                    @foreach ([
                        'Committee Referred' => $agenda->committee_referred,
                        'Date of Referral' => $agenda->date_of_referral?->format('M d, Y'),
                        'Outcome' => $agenda->outcome,
                    ] as $label => $value)
                        @if ($value !== null && $value !== '')
                            <div class="splis-detail-row">
                                <dt class="splis-detail-label">{{ $label }}</dt>
                                <dd class="splis-detail-value">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                    @if (! $agenda->committee_referred && ! $agenda->date_of_referral && ! $agenda->outcome)
                        <p class="px-4 py-6 text-sm text-slate-500">No committee action recorded yet.</p>
                    @endif
                </dl>
            </div>
        </div>

        <div class="splis-detail-sidebar-column">
            <aside class="splis-card splis-agenda-tracking-card overflow-hidden">
                <div class="splis-card-header splis-card-header--emphasis">
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
                </div>
            </aside>

            @if ($agenda->resolution || $agenda->ordinance || $agenda->appropriationOrdinance)
                <aside class="splis-card overflow-hidden">
                    <div class="splis-card-header splis-card-header--emphasis">
                        <h2 class="splis-card-title">Legislative Measure</h2>
                    </div>
                    <div class="splis-card-body space-y-4">
                        @if ($agenda->resolution)
                            <div>
                                <p class="splis-detail-label">Resolution</p>
                                <a href="{{ route('resolutions.show', $agenda->resolution) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-200">
                                    {{ $agenda->resolution->resolution_no }} / {{ $agenda->resolution->series }}
                                </a>
                            </div>
                        @endif
                        @if ($agenda->ordinance)
                            <div>
                                <p class="splis-detail-label">Ordinance</p>
                                <a href="{{ route('ordinances.show', $agenda->ordinance) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-200">
                                    {{ $agenda->ordinance->displayNumber() }} ({{ $agenda->ordinance->series_year }})
                                </a>
                            </div>
                        @endif
                        @if ($agenda->appropriationOrdinance)
                            <div>
                                <p class="splis-detail-label">Appropriation Ordinance</p>
                                <a href="{{ route('appropriation-ordinances.show', $agenda->appropriationOrdinance) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-200">
                                    {{ $agenda->appropriationOrdinance->displayNumber() }} ({{ $agenda->appropriationOrdinance->series_year }})
                                </a>
                            </div>
                        @endif
                    </div>
                </aside>
            @endif
        </div>
    </div>

    @include('agenda.partials.splis-activity-logs', [
        'splisActivityLogs' => $splisActivityLogs ?? collect(),
        'obPlacementCount' => $obPlacementCount ?? 0,
        'emphasisHeaders' => true,
    ])
</div>
@endsection
