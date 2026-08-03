@extends('layouts.app')

@section('title', 'Summary of Committee Reports Maker — '.$session->displayTitle())

@section('content')
@php
    $groups = $content['groups'] ?? [];
    $preparedBy = $content['prepared_by'] ?? ['name' => '', 'title' => ''];
    $reviewedBy = $content['reviewed_by'] ?? ['name' => '', 'title' => ''];
    $titlePlain = old('title', $summary->title);
    $titleHtml = old('title_html', $content['title_html'] ?? '');
    $recommendationTemplates = config('order_of_business.committee_report_summary.recommendation_templates', []);
    $committeeReportFiles = $committeeReportFiles ?? collect();
    $committeeReportsDriveUrl = trim((string) ($committeeReportsDriveUrl ?? ''));
    $hasCommitteeReportsFolder = $committeeReportFiles->isNotEmpty() || $committeeReportsDriveUrl !== '';
@endphp

<div class="max-w-5xl" id="scr-maker">
    <div class="splis-page-header !mb-4">
        <div class="min-w-0">
            <p class="text-sm text-slate-500">{{ $session->displayTitle() }}</p>
            <h1 class="splis-page-title">Summary of Committee Reports Maker</h1>
            <p class="splis-page-subtitle">
                Content is loaded from OB Section IV (Committee Reports). Edit freely, then Save.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($hasCommitteeReportsFolder)
                <button
                    type="button"
                    class="splis-btn-secondary inline-flex items-center gap-2"
                    data-folder-modal-open
                    data-folder-modal-target="#scr-committee-reports-folder-modal"
                >
                    <x-icon name="file-text" class="h-4 w-4" />
                    @if ($committeeReportFiles->isNotEmpty())
                        View Committee Reports ({{ $committeeReportFiles->count() }})
                    @else
                        View Committee Reports
                    @endif
                </button>
            @endif
            <a
                href="{{ route('ob.sessions.committee-report-summary.print', $session) }}"
                data-pdf-modal-open
                data-pdf-viewer="iframe"
                data-pdf-src="{{ route('ob.sessions.committee-report-summary.print', $session) }}?embed=1"
                data-pdf-url="{{ route('ob.sessions.committee-report-summary.print', $session) }}"
                data-pdf-title="Summary of Committee Reports"
                class="splis-btn-secondary inline-flex items-center gap-2"
            >
                <x-icon name="printer" class="h-4 w-4" />
                Print Preview
            </a>
            <a href="{{ route('ob.sessions.show', $session) }}" class="splis-btn-secondary">Session</a>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('ob.sessions.committee-report-summary.sync', $session) }}" data-scr-sync-form>
                @csrf
                <button type="submit" class="splis-btn-secondary inline-flex items-center gap-2">
                    <x-icon name="refresh" class="h-4 w-4" />
                    Refresh from OB
                </button>
            </form>
            <p id="scr-save-status" class="text-sm text-slate-500" aria-live="polite">All changes saved</p>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2">
            <a
                href="{{ route('ob.sessions.show', $session) }}"
                id="scr-back-to-session"
                class="splis-btn-secondary"
                data-scr-back
                title="Back to session"
            >
                Back
            </a>
            <button
                type="submit"
                form="scr-maker-form"
                id="scr-save-document"
                class="splis-btn-primary inline-flex items-center gap-2"
                disabled
                title="Save all changes (Ctrl+S)"
            >
                <x-icon name="check-circle" class="h-4 w-4" />
                Save
            </button>
        </div>
    </div>

    <form
        id="scr-maker-form"
        method="POST"
        action="{{ route('ob.sessions.committee-report-summary.update', $session) }}"
        class="space-y-6"
        data-scr-maker-form
    >
        @csrf
        @method('PUT')

        <div class="splis-card splis-card-body grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                @include('order-of-business.committee-report-summary.partials.rich-editor', [
                    'label' => 'Document title',
                    'editorId' => 'scr-title',
                    'name' => 'title',
                    'htmlName' => 'title_html',
                    'plain' => $titlePlain,
                    'html' => $titleHtml,
                    'editorClass' => '!min-h-16 text-center font-semibold uppercase',
                ])
            </div>
            <div>
                <label class="splis-label" for="report_date">Report date</label>
                <input type="date" name="report_date" id="report_date" class="splis-input" value="{{ old('report_date', $summary->report_date?->format('Y-m-d')) }}">
            </div>
        </div>

        @forelse ($groups as $groupIndex => $group)
            <div class="splis-card overflow-hidden">
                <div class="splis-card-header splis-card-header--emphasis">
                    <h2 class="splis-card-title">
                        <span class="mr-1.5 inline-flex min-w-[1.75rem] justify-center text-brand-700 dark:text-brand-gold">{{ $groupIndex + 1 }}.</span>{{ $group['committee_name'] ?? 'Committee' }}
                    </h2>
                    <p class="splis-card-subtitle">{{ app(\App\Services\CommitteeReportSummaryService::class)->formatChairDisplay($group['chair_name'] ?? '') }}</p>
                </div>
                <div class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach ($group['items'] ?? [] as $item)
                        @php
                            $itemKey = app(\App\Services\CommitteeReportSummaryService::class)->itemKey($item);
                            $bodyPlain = old('bodies.'.$itemKey, $item['body'] ?? '');
                            $bodyHtml = old('bodies_html.'.$itemKey, $item['body_html'] ?? '');
                            $revisedLabel = old('revised_title_labels.'.$itemKey, $item['revised_title_label'] ?? 'REVISED TITLE');
                            $revisedPlain = old('revised_titles.'.$itemKey, $item['revised_title'] ?? '');
                            $revisedHtml = old('revised_titles_html.'.$itemKey, $item['revised_title_html'] ?? '');
                            $hasRevisedTitle = filled($revisedPlain) || filled($revisedHtml);
                            $recPlain = old('recommendations.'.$itemKey, $item['recommendation'] ?? '');
                            $recHtml = old('recommendations_html.'.$itemKey, $item['recommendation_html'] ?? '');
                        @endphp
                        <div class="space-y-4 px-4 py-4">
                            <p class="text-base font-bold text-slate-900 dark:text-slate-50">
                                <span class="inline bg-[#fff200] px-1.5 py-0.5 text-slate-900">Agenda No. {{ $item['agenda_no'] ?? '—' }}</span>
                            </p>
                            @include('order-of-business.committee-report-summary.partials.rich-editor', [
                                'label' => 'Title / description',
                                'editorId' => 'scr-body-'.$itemKey,
                                'name' => 'bodies['.$itemKey.']',
                                'htmlName' => 'bodies_html['.$itemKey.']',
                                'plain' => $bodyPlain,
                                'html' => $bodyHtml,
                                'editorClass' => '!min-h-24 text-justify',
                            ])

                            <div data-scr-revised-wrap data-open="{{ $hasRevisedTitle ? '1' : '0' }}">
                                <div class="{{ $hasRevisedTitle ? 'hidden' : '' }}" data-scr-revised-add-row>
                                    <button type="button" class="splis-btn-ghost text-sm" data-scr-revised-add>
                                        + Add revised title
                                    </button>
                                </div>
                                <div class="space-y-4 {{ $hasRevisedTitle ? '' : 'hidden' }}" data-scr-revised-fields>
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Revised title (optional)</p>
                                        <button type="button" class="splis-btn-ghost text-sm text-red-600 hover:text-red-700" data-scr-revised-remove>
                                            Remove
                                        </button>
                                    </div>
                                    <div>
                                        <label class="splis-label" for="scr-revised-label-{{ $itemKey }}">REVISED TITLE</label>
                                        <input
                                            type="text"
                                            id="scr-revised-label-{{ $itemKey }}"
                                            name="revised_title_labels[{{ $itemKey }}]"
                                            class="splis-input font-semibold uppercase"
                                            value="{{ $revisedLabel }}"
                                            placeholder="REVISED TITLE"
                                            @disabled(! $hasRevisedTitle)
                                            data-scr-revised-label
                                        >
                                    </div>
                                    @include('order-of-business.committee-report-summary.partials.rich-editor', [
                                        'label' => 'Text',
                                        'editorId' => 'scr-revised-'.$itemKey,
                                        'name' => 'revised_titles['.$itemKey.']',
                                        'htmlName' => 'revised_titles_html['.$itemKey.']',
                                        'plain' => $revisedPlain,
                                        'html' => $revisedHtml,
                                        'editorClass' => '!min-h-20 text-justify uppercase',
                                        'hint' => 'Optional. Supports bold, underline, and highlight.',
                                    ])
                                </div>
                            </div>

                            @include('order-of-business.committee-report-summary.partials.rich-editor', [
                                'label' => 'RECOMMENDATION',
                                'editorId' => 'scr-rec-'.$itemKey,
                                'name' => 'recommendations['.$itemKey.']',
                                'htmlName' => 'recommendations_html['.$itemKey.']',
                                'plain' => $recPlain,
                                'html' => $recHtml,
                                'editorClass' => '!min-h-16 font-semibold uppercase text-justify',
                                'templates' => $recommendationTemplates,
                                'hint' => 'Click a template to fill quickly, then edit. Use H for yellow highlights.',
                            ])
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="splis-card splis-card-body text-sm text-slate-500">
                No Committee Report agenda items were found on this session’s Order of Business.
                Place agendas under <strong>IV. Committee Reports</strong>, then click <strong>Refresh from OB</strong>.
            </div>
        @endforelse

        <div class="splis-card splis-card-body grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Prepared by</h3>
                <div>
                    <label class="splis-label" for="prepared_by_name">Name</label>
                    <input type="text" name="prepared_by[name]" id="prepared_by_name" class="splis-input" value="{{ old('prepared_by.name', $preparedBy['name'] ?? '') }}">
                </div>
                <div>
                    <label class="splis-label" for="prepared_by_title">Title</label>
                    <input type="text" name="prepared_by[title]" id="prepared_by_title" class="splis-input" value="{{ old('prepared_by.title', $preparedBy['title'] ?? '') }}">
                </div>
            </div>
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Reviewed by</h3>
                <div>
                    <label class="splis-label" for="reviewed_by_name">Name</label>
                    <input type="text" name="reviewed_by[name]" id="reviewed_by_name" class="splis-input" value="{{ old('reviewed_by.name', $reviewedBy['name'] ?? '') }}">
                </div>
                <div>
                    <label class="splis-label" for="reviewed_by_title">Title</label>
                    <input type="text" name="reviewed_by[title]" id="reviewed_by_title" class="splis-input" value="{{ old('reviewed_by.title', $reviewedBy['title'] ?? '') }}">
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <a href="{{ route('ob.sessions.committee-report-summary.print', $session) }}" target="_blank" class="splis-btn-secondary">
                Open Print Page
            </a>
        </div>
    </form>
</div>

@if ($hasCommitteeReportsFolder)
    @include('partials.document-folder-modal', [
        'modalId' => 'scr-committee-reports-folder-modal',
        'title' => 'Committee Reports — '.$session->displayTitle(),
        'files' => $committeeReportFiles,
        'driveUrl' => $committeeReportsDriveUrl,
    ])
@endif
@endsection
