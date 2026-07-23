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

    .scr-print-table {
        width: 100%;
        border-collapse: collapse;
        border: 1.5pt solid #000;
    }

    .scr-print-table td {
        border: 1pt solid #000;
        vertical-align: top;
        padding: 0.45rem 0.55rem;
    }

    .scr-print-col-no {
        width: 2.1rem;
        text-align: center;
        font-weight: 700;
        font-size: 12pt;
        vertical-align: middle;
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

    <table class="scr-print-table">
        <tbody>
            @forelse ($groups as $index => $group)
                {{-- Row 1: number | committee + chair --}}
                <tr>
                    <td class="scr-print-col-no">{{ $index + 1 }}.</td>
                    <td>
                        <p class="scr-print-committee">{{ $group['committee_name'] ?? '' }}</p>
                        <p class="scr-print-chair">{{ app(\App\Services\CommitteeReportSummaryService::class)->formatChairDisplay($group['chair_name'] ?? '') }}</p>
                    </td>
                </tr>
                {{-- Row 2: empty number cell | agenda titles + recommendations --}}
                <tr>
                    <td class="scr-print-col-no"></td>
                    <td>
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
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" style="padding: 1rem; text-align: center;">No committee report items.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

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
