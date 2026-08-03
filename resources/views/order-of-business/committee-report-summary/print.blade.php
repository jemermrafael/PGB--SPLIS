@extends('layouts.print')

@section('title', 'Print — '.$summary->title)

@push('head')
<style>
    @page {
        size: 8.5in 13in;
        margin: 0.6in 0.7in;
    }

    @media print {
        .scr-print-toolbar { display: none !important; }
    }

    .scr-print-document {
        max-width: 8.5in;
        margin: 0 auto;
        font-family: Verdana, Geneva, sans-serif;
        font-size: 11pt;
        color: #000;
        line-height: 1.35;
    }

    .scr-print-title-box {
        border: 1.5pt solid #000;
        padding: 0.35rem 0.75rem 0.55rem;
        text-align: center;
        margin-bottom: 0.85rem;
    }

    .scr-print-title {
        margin: 0;
        font-size: 14pt;
        font-weight: 700;
        text-underline-offset: 2px;
        display: inline-block;
        padding: 0 0.15rem;
    }

    .scr-print-title:not(:has(*)) {
        text-decoration: underline;
        background: #fff200;
        text-transform: uppercase;
    }

    .scr-print-title u {
        text-decoration: underline;
    }

    .scr-print-title mark,
    .scr-print-item mark,
    .scr-print-recommendation mark {
        background: #fff200;
        color: inherit;
    }

    .scr-print-title strong,
    .scr-print-item strong,
    .scr-print-recommendation strong {
        font-weight: 700;
    }

    .scr-print-date {
        margin: 0.35rem 0 0;
        font-size: 12pt;
        font-weight: 700;
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    /*
     * 2x2 CSS grid (4 cells). Borders use box-decoration-break + vertical
     * background gradients so left/right edges survive Chrome page fragments.
     * Padding lives on .scr-print-cell-inner only (not the bordered box).
     */
    .scr-print-group {
        margin-bottom: 0.85rem;
    }

    .scr-print-group:last-of-type {
        margin-bottom: 0;
    }

    .scr-print-grid {
        display: grid;
        grid-template-columns: 2.1rem minmax(0, 1fr);
        grid-template-rows: auto auto;
        width: 100%;
        border: 1.5pt solid #000;
        -webkit-box-decoration-break: clone;
        box-decoration-break: clone;
        background-color: #fff;
        background-image:
            linear-gradient(#000, #000),
            linear-gradient(#000, #000);
        background-size: 1.5pt 100%, 1.5pt 100%;
        background-position: left top, right top;
        background-repeat: repeat-y;
    }

    .scr-print-cell {
        min-width: 0;
        padding: 0;
        margin: 0;
        background-color: #fff;
        -webkit-box-decoration-break: clone;
        box-decoration-break: clone;
    }

    .scr-print-cell-inner {
        padding: 0.45rem 0.55rem;
    }

    /* Number column: right divider (cloned + gradient for fragments). */
    .scr-print-cell--no,
    .scr-print-cell--no-empty {
        text-align: center;
        font-weight: 700;
        font-size: 12pt;
        background-image: linear-gradient(#ffffff, #ffffff);
        background-size: 1.5pt 100%;
        background-position: right top;
        background-repeat: repeat-y;
        background-color: #fff;
    }

    .scr-print-cell--no .scr-print-cell-inner {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100%;
        padding-left: 0.15rem;
        padding-right: 0.15rem;
    }

    /* Content column: left divider backup so the split line stays on page 2+. */
    .scr-print-cell--committee,
    .scr-print-cell--body {
        background-image: linear-gradient(#000, #000);
        background-size: 1px 100%;
        background-position: left top;
        background-repeat: repeat-y;
        background-color: #fff;
    }

    /* Horizontal split between header row and body row. */
    .scr-print-cell--no,
    .scr-print-cell--committee {
        border-bottom: 1px solid #000;
    }

    .scr-print-committee {
        text-align: center;
        font-weight: 700;
        text-decoration: underline;
        text-underline-offset: 2px;
        text-transform: uppercase;
        margin: 0 0 0.15rem;
        font-size: 11.5pt;
    }

    .scr-print-chair {
        text-align: center;
        font-weight: 700;
        margin: 0;
        font-size: 11pt;
    }

    .scr-print-item {
        margin: 0 0 0.85rem;
        text-align: justify;
        text-justify: inter-word;
    }

    .scr-print-item:last-child {
        margin-bottom: 0.15rem;
    }

    .scr-print-agenda-no {
        font-weight: 700;
        background: #fff200;
        padding: 0 0.1rem;
    }

    .scr-print-revised-title {
        margin: 0.45rem 0 0;
        text-align: justify;
        text-justify: inter-word;
    }

    .scr-print-revised-title-label {
        font-weight: 700;
        background: #fff200;
        padding: 0 0.1rem;
        text-transform: uppercase;
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .scr-print-revised-title-text {
        display: block;
        margin-top: 0.25rem;
        text-transform: uppercase;
    }

    .scr-print-recommendation {
        margin: 0.45rem 0 0;
        font-weight: 700;
        text-align: justify;
        text-justify: inter-word;
    }

    .scr-print-recommendation-label {
        font-weight: 700;
        text-decoration: none;
    }

    .scr-print-recommendation-text {
        font-weight: 700;
        text-decoration: underline;
        text-underline-offset: 2px;
        text-transform: uppercase;
    }

    .scr-print-recommendation-text u {
        text-decoration: underline;
    }

    .scr-print-empty {
        text-align: center;
        padding: 1rem 0.55rem;
    }

    .scr-print-signatories {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-top: 2.25rem;
        text-align: center;
    }

    .scr-print-sign-label {
        margin: 0 0 1.75rem;
        font-size: 11pt;
    }

    .scr-print-sign-name {
        margin: 0;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 11pt;
    }

    .scr-print-sign-title {
        margin: 0.1rem 0 0;
        font-size: 10.5pt;
    }
</style>
@endpush

@php
    $groups = $content['groups'] ?? [];
    $preparedBy = $content['prepared_by'] ?? ['name' => '', 'title' => ''];
    $reviewedBy = $content['reviewed_by'] ?? ['name' => '', 'title' => ''];
    $reportDate = $summary->report_date?->format('F j, Y') ?? $session->session_date?->format('F j, Y');
    $titleHtml = $content['title_html'] ?? null;
@endphp

@section('content')
<div class="scr-print-toolbar sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3 print:hidden">
    <div>
        <p class="font-semibold text-slate-900">{{ $summary->title }}</p>
        <p class="text-sm text-slate-600">{{ $session->displayTitle() }}</p>
    </div>
    <div class="flex gap-2">
        @unless ($isEmbeddedPreview)
            @can('update', $session)
                <a href="{{ route('ob.sessions.committee-report-summary.maker', $session) }}" class="splis-btn-secondary inline-flex items-center gap-2">
                    <x-icon name="arrow-left" class="h-4 w-4" />
                    Back to Maker
                </a>
            @else
                <a href="{{ route('ob.sessions.show', $session) }}" class="splis-btn-secondary inline-flex items-center gap-2">
                    <x-icon name="arrow-left" class="h-4 w-4" />
                    Back
                </a>
            @endcan
        @endunless
        <button type="button" class="splis-btn-primary inline-flex items-center gap-2" onclick="window.print()">
            <x-icon name="printer" class="h-4 w-4" />
            Print / Save as PDF
        </button>
    </div>
</div>

<article class="scr-print-document px-6 py-8">
    <div class="scr-print-title-box">
        <h1 class="scr-print-title">
            @if (filled($titleHtml))
                {!! $titleHtml !!}
            @else
                {{ mb_strtoupper($summary->title ?: 'SUMMARY OF COMMITTEE REPORT') }}
            @endif
        </h1>
        @if ($reportDate)
            <p class="scr-print-date">{{ mb_strtoupper($reportDate) }}</p>
        @endif
    </div>

    @forelse ($groups as $index => $group)
        <div class="scr-print-group">
            <div class="scr-print-grid">
                {{-- Row 1 --}}
                <div class="scr-print-cell scr-print-cell--no">
                    <div class="scr-print-cell-inner">{{ $index + 1 }}.</div>
                </div>
                <div class="scr-print-cell scr-print-cell--committee">
                    <div class="scr-print-cell-inner">
                        <p class="scr-print-committee">{{ $group['committee_name'] ?? '' }}</p>
                        <p class="scr-print-chair">{{ app(\App\Services\CommitteeReportSummaryService::class)->formatChairDisplay($group['chair_name'] ?? '') }}</p>
                    </div>
                </div>
                {{-- Row 2 --}}
                <div class="scr-print-cell scr-print-cell--no-empty" aria-hidden="true">
                    <div class="scr-print-cell-inner"></div>
                </div>
                <div class="scr-print-cell scr-print-cell--body">
                    <div class="scr-print-cell-inner">
                        @foreach ($group['items'] ?? [] as $item)
                            <div class="scr-print-item">
                                <p>
                                    <span class="scr-print-agenda-no">Agenda No. {{ $item['agenda_no'] ?? '—' }}</span>@if (filled($item['body'] ?? null) || filled($item['body_html'] ?? null))
                                        —
                                        @if (filled($item['body_html'] ?? null))
                                            {!! $item['body_html'] !!}
                                        @else
                                            {!! nl2br(e($item['body'])) !!}
                                        @endif
                                    @endif
                                </p>
                                @if (filled($item['revised_title'] ?? null) || filled($item['revised_title_html'] ?? null))
                                    <p class="scr-print-revised-title">
                                        <span class="scr-print-revised-title-label">{{ $item['revised_title_label'] ?? 'REVISED TITLE' }}:</span>
                                        <span class="scr-print-revised-title-text">
                                            @if (filled($item['revised_title_html'] ?? null))
                                                {!! $item['revised_title_html'] !!}
                                            @else
                                                {!! nl2br(e($item['revised_title'])) !!}
                                            @endif
                                        </span>
                                    </p>
                                @endif
                                @if (filled($item['recommendation'] ?? null) || filled($item['recommendation_html'] ?? null))
                                    <p class="scr-print-recommendation">
                                        <span class="scr-print-recommendation-label">RECOMMENDATION:</span>
                                        <span class="scr-print-recommendation-text">
                                            @if (filled($item['recommendation_html'] ?? null))
                                                {!! $item['recommendation_html'] !!}
                                            @else
                                                {{ mb_strtoupper(trim($item['recommendation'])) }}
                                            @endif
                                        </span>
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="scr-print-group">
            <div class="scr-print-grid">
                <div class="scr-print-cell scr-print-cell--body" style="grid-column: 1 / -1;">
                    <div class="scr-print-cell-inner scr-print-empty">No committee report items.</div>
                </div>
            </div>
        </div>
    @endforelse

    <div class="scr-print-signatories">
        <div>
            <p class="scr-print-sign-label">Prepared by:</p>
            <p class="scr-print-sign-name">{{ $preparedBy['name'] ?: '________________' }}</p>
            <p class="scr-print-sign-title">{{ $preparedBy['title'] ?: '' }}</p>
        </div>
        <div>
            <p class="scr-print-sign-label">Reviewed by:</p>
            <p class="scr-print-sign-name">{{ $reviewedBy['name'] ?: '________________' }}</p>
            <p class="scr-print-sign-title">{{ $reviewedBy['title'] ?: '' }}</p>
        </div>
    </div>
</article>
@endsection
