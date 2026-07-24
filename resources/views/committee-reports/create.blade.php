@extends('layouts.app')

@section('title', 'Submit Committee Report — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Submit Committee Report</h1>
            <p class="splis-page-subtitle">Submit on behalf of a Board Member chair. Tag open chairmanship agendas for that member.</p>
        </div>
        <a href="{{ route('committee-reports.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Back
        </a>
    </div>

    @include('committee-reports._form', [
        'report' => null,
        'chairMembers' => $chairMembers,
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
