@extends('layouts.app')

@section('title', 'Summary of Committee Reports Maker — '.$session->displayTitle())

@section('content')
@php
    $groups = $content['groups'] ?? [];
    $preparedBy = $content['prepared_by'] ?? ['name' => '', 'title' => ''];
    $reviewedBy = $content['reviewed_by'] ?? ['name' => '', 'title' => ''];
    $titlePlain = old('title', $summary->title);
    $titleHtml = old('title_html', $content['title_html'] ?? '');
@endphp

<div class="max-w-5xl" id="scr-maker">
    <div class="splis-page-header !mb-4">
        <div class="min-w-0">
            <p class="text-sm text-slate-500">{{ $session->displayTitle() }}</p>
            <h1 class="splis-page-title">Summary of Committee Reports Maker</h1>
            <p class="splis-page-subtitle">
                Content is loaded from OB Section IV (Committee Reports). Edit titles and recommendations with bold, underline, and highlight.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
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

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('ob.sessions.committee-report-summary.sync', $session) }}">
            @csrf
            <button type="submit" class="splis-btn-secondary inline-flex items-center gap-2">
                <x-icon name="refresh" class="h-4 w-4" />
                Refresh from OB
            </button>
        </form>
        <p id="scr-save-status" class="text-sm text-slate-500" aria-live="polite">Changes save automatically</p>
    </div>

    <form method="POST" action="{{ route('ob.sessions.committee-report-summary.update', $session) }}" class="space-y-6" data-scr-maker-form>
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
                <div class="splis-card-header">
                    <h2 class="splis-card-title">{{ $groupIndex + 1 }}. {{ $group['committee_name'] ?? 'Committee' }}</h2>
                    <p class="splis-card-subtitle">{{ app(\App\Services\CommitteeReportSummaryService::class)->formatChairDisplay($group['chair_name'] ?? '') }}</p>
                </div>
                <div class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach ($group['items'] ?? [] as $item)
                        @php
                            $itemKey = app(\App\Services\CommitteeReportSummaryService::class)->itemKey($item);
                            $bodyPlain = old('bodies.'.$itemKey, $item['body'] ?? '');
                            $bodyHtml = old('bodies_html.'.$itemKey, $item['body_html'] ?? '');
                            $recPlain = old('recommendations.'.$itemKey, $item['recommendation'] ?? '');
                            $recHtml = old('recommendations_html.'.$itemKey, $item['recommendation_html'] ?? '');
                        @endphp
                        <div class="space-y-4 px-4 py-4">
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                Agenda No. {{ $item['agenda_no'] ?? '—' }}
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
                            @include('order-of-business.committee-report-summary.partials.rich-editor', [
                                'label' => 'RECOMMENDATION',
                                'editorId' => 'scr-rec-'.$itemKey,
                                'name' => 'recommendations['.$itemKey.']',
                                'htmlName' => 'recommendations_html['.$itemKey.']',
                                'plain' => $recPlain,
                                'html' => $recHtml,
                                'editorClass' => '!min-h-16 font-semibold uppercase text-justify',
                                'hint' => 'Print shows this text bold and underlined. Use H only for yellow highlights you want.',
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

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="splis-btn-primary inline-flex items-center gap-2">
                <x-icon name="check-circle" class="h-4 w-4" />
                Save now
            </button>
            <a href="{{ route('ob.sessions.committee-report-summary.print', $session) }}" target="_blank" class="splis-btn-secondary">
                Open Print Page
            </a>
        </div>
    </form>
</div>
@endsection
