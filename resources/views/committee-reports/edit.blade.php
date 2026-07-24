@extends('layouts.app')

@section('title', 'Edit Committee Report — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Edit Committee Report</h1>
            <p class="splis-page-subtitle">
                For {{ $report->boardMember?->displayName() ?? 'Board Member' }}
                · submitted {{ $report->submitted_at?->format('M j, Y g:i A') }}
            </p>
        </div>
        <a href="{{ route('committee-reports.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Back
        </a>
    </div>

    @include('committee-reports._form', [
        'report' => $report,
        'chairMembers' => collect(),
        'boardMemberId' => $boardMemberId,
        'q' => $q,
        'committeeId' => $committeeId,
        'chairCommittees' => $chairCommittees,
        'agendaItems' => $agendaItems,
        'selectedAgendaIds' => $selectedAgendaIds,
        'agendaSearchUrl' => $agendaSearchUrl,
    ])
</div>
@endsection
