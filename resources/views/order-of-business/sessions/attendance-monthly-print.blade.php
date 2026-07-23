@extends('layouts.print')

@section('title', 'Print — Attendance '.$monthLabel)

@push('head')
<style>
    @page {
        size: 8.5in 13in;
        margin: 0.55in 0.5in;
    }

    @media print {
        .att-print-toolbar { display: none !important; }
    }

    .att-print-document {
        max-width: 8.5in;
        margin: 0 auto;
        font-family: Verdana, Geneva, sans-serif;
        font-size: 10pt;
        color: #000;
        line-height: 1.3;
    }

    .att-print-title {
        text-align: center;
        margin: 0 0 0.15rem;
        font-size: 13pt;
        font-weight: 700;
        text-transform: uppercase;
    }

    .att-print-subtitle {
        text-align: center;
        margin: 0 0 0.9rem;
        font-size: 11pt;
        font-weight: 700;
    }

    .att-print-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: auto;
    }

    .att-print-table th,
    .att-print-table td {
        border: 1pt solid #000;
        vertical-align: middle;
        padding: 0.28rem 0.35rem;
    }

    .att-print-table th {
        font-size: 9pt;
        font-weight: 700;
        text-align: center;
    }

    .att-print-col-name {
        text-align: left;
        width: 1%;
        white-space: nowrap;
        padding-right: 2rem;
    }

    .att-print-col-session {
        text-align: center;
        width: 3.5rem;
        min-width: 3.5rem;
    }

    .att-print-col-remarks {
        text-align: left;
        font-size: 9pt;
        width: 7.15rem;
        min-width: 7.15rem;
        max-width: 7.15rem;
    }

    .att-print-session-code {
        display: block;
        line-height: 1.15;
    }

    .att-print-section {
        font-weight: 700;
        background: #f3f3f3;
    }

    .att-print-member-name {
        margin: 0;
        font-weight: 700;
        padding-left: 1.25rem;
        text-wrap: nowrap;
        white-space: nowrap;
    }

    .att-print-member-name--plain {
        padding-left: 0;
    }

    .att-print-member-subtitle {
        margin: 0.1rem 0 0;
        font-size: 9pt;
        font-weight: 400;
        padding-left: 1.25rem;
    }

    .att-print-member-subtitle--plain {
        padding-left: 0;
    }

    .att-print-mark {
        font-weight: 700;
        font-size: 11pt;
    }

    .att-print-footer {
        margin-top: 1.25rem;
        display: grid;
        grid-template-columns: 1.1fr 1fr 1fr;
        gap: 1rem 1.5rem;
        align-items: start;
    }

    .att-print-legend {
        font-size: 7.5pt;
    }

    .att-print-legend-title {
        margin: 0 0 0.35rem;
        font-weight: 700;
    }

    .att-print-legend-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .att-print-legend-list li {
        margin: 0.1rem 0;
    }

    .att-print-sign {
        text-align: center;
        font-size: 10pt;
    }

    .att-print-sign-label {
        margin: 0 0 1.6rem;
    }

    .att-print-sign-name {
        margin: 0;
        font-weight: 700;
        text-transform: uppercase;
        text-wrap: nowrap;
        white-space: nowrap;
    }

    .att-print-sign-title {
        margin: 0.1rem 0 0;
    }

    .att-print-approved {
        grid-column: 1 / -1;
        display: flex;
        justify-content: center;
        margin-top: 0.75rem;
    }

    .att-print-approved .att-print-sign {
        min-width: 14rem;
    }
</style>
@endpush

@php
    $colCount = 1 + count($payload['sessions']) + 1;
@endphp

@section('content')
<div class="att-print-toolbar sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3 print:hidden">
    <div>
        <p class="font-semibold text-slate-900">{{ $payload['title'] }}</p>
        <p class="text-sm text-slate-600">for the month of {{ strtoupper($monthLabel) }}</p>
    </div>
    <div class="flex gap-2">
        @unless ($isEmbeddedPreview)
            <a href="{{ route('ob.sessions.attendance.monthly.maker', ['year' => $year, 'month' => $month]) }}" class="splis-btn-secondary inline-flex items-center gap-2">
                <x-icon name="edit" class="h-4 w-4" />
                Edit Signatories
            </a>
            <a href="{{ route('ob.sessions.attendance.monthly', ['year' => $year, 'month' => $month]) }}" class="splis-btn-secondary inline-flex items-center gap-2">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Back
            </a>
        @endunless
        <button type="button" class="splis-btn-primary inline-flex items-center gap-2" onclick="window.print()">
            <x-icon name="printer" class="h-4 w-4" />
            Print / Save as PDF
        </button>
    </div>
</div>

<article class="att-print-document px-6 py-8">
    <h1 class="att-print-title">{{ $payload['title'] }}</h1>
    <p class="att-print-subtitle">for the month of {{ strtoupper($monthLabel) }}</p>

    <table class="att-print-table">
        <thead>
            <tr>
                <th class="att-print-col-name"></th>
                @foreach ($payload['sessions'] as $session)
                    <th class="att-print-col-session">
                        @if ($session)
                            <span class="att-print-session-code">{{ app(\App\Services\SessionAttendanceService::class)->sessionColumnCode($session) }}</span>
                            <span class="att-print-session-code">{{ app(\App\Services\SessionAttendanceService::class)->sessionColumnDay($session) }}</span>
                        @endif
                    </th>
                @endforeach
                <th class="att-print-col-remarks">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payload['rows'] as $row)
                @if ($row['type'] === 'section')
                    <tr>
                        <td class="att-print-section">{{ $row['label'] }}</td>
                        @foreach ($payload['sessions'] as $session)
                            <td class="att-print-col-session"></td>
                        @endforeach
                        <td class="att-print-col-remarks"></td>
                    </tr>
                @else
                    @php
                        $isViceGovernor = ($row['subtitle'] ?? null) === 'Provincial Vice-Governor';
                    @endphp
                    <tr>
                        <td class="att-print-col-name">
                            <p @class(['att-print-member-name', 'att-print-member-name--plain' => $isViceGovernor])>
                                {{ $row['name'] }}
                            </p>
                            @if (filled($row['subtitle'] ?? null))
                                <p @class(['att-print-member-subtitle', 'att-print-member-subtitle--plain' => $isViceGovernor])>
                                    {{ $row['subtitle'] }}
                                </p>
                            @endif
                        </td>
                        @foreach ($row['marks'] ?? [] as $mark)
                            <td class="att-print-col-session">
                                @php $symbol = \App\Models\SessionAttendance::printMarkFor($mark); @endphp
                                @if ($symbol !== '')
                                    <span class="att-print-mark">{{ $symbol }}</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="att-print-col-remarks">{{ $row['remarks'] ?? '' }}</td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="{{ $colCount }}" style="text-align: center; padding: 1rem;">
                        No roster members for this term.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="att-print-footer">
        <div class="att-print-legend">
            <p class="att-print-legend-title">Legend:</p>
            <ul class="att-print-legend-list">
                <li>/ &nbsp;- Present</li>
                <li>X &nbsp;- Absent</li>
                <li>OB - Official Business</li>
                <li>* &nbsp;- Excused</li>
                <li>RS - Regular Session</li>
                <li>SS - Special Session</li>
            </ul>
        </div>

        <div class="att-print-sign">
            <p class="att-print-sign-label">Prepared by:</p>
            <p class="att-print-sign-name">{{ $payload['prepared_by']['name'] ?: '________________' }}</p>
            <p class="att-print-sign-title">{{ $payload['prepared_by']['title'] ?: '' }}</p>
        </div>

        <div class="att-print-sign">
            <p class="att-print-sign-label">Noted by:</p>
            <p class="att-print-sign-name">{{ $payload['noted_by']['name'] ?: '________________' }}</p>
            <p class="att-print-sign-title">{{ $payload['noted_by']['title'] ?: '' }}</p>
        </div>

        <div class="att-print-approved">
            <div class="att-print-sign">
                <p class="att-print-sign-label">Approved by:</p>
                <p class="att-print-sign-name">{{ $payload['approved_by']['name'] ?: '________________' }}</p>
                <p class="att-print-sign-title">{{ $payload['approved_by']['title'] ?: '' }}</p>
            </div>
        </div>
    </div>
</article>
@endsection
