@extends('layouts.app')

@section('title', 'Session attendance — '.$session->displayTitle().' — '.config('app.name'))

@section('content')
<div class="max-w-4xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">Session attendance</h1>
            <p class="splis-page-subtitle">{{ $session->displayTitle() }} · {{ $session->session_date->format('F j, Y') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('ob.sessions.attendance.monthly') }}" class="splis-btn-secondary">Monthly report</a>
            <a href="{{ route('ob.sessions.show', $session) }}" class="splis-btn-secondary">Back to session</a>
        </div>
    </div>

    @if (session('status'))
        <div class="splis-alert-success mb-6">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('ob.sessions.attendance.update', $session) }}" class="splis-card splis-card-body space-y-4">
        @csrf
        @method('PUT')

        <p class="text-sm text-slate-600 dark:text-slate-400">Mark Vice Governor and board members present for this session.</p>

        <div class="divide-y divide-slate-200 dark:divide-slate-700">
            @foreach ($roster as $member)
                @php
                    $district = $member->districtForTerm($termId ?? null) ?? $member->district ?? '';
                    $attendance = $attendances->get($member->id);
                @endphp
                <label class="flex items-center justify-between gap-4 py-3">
                    <span>
                        <span class="font-medium text-slate-900 dark:text-slate-100">{{ $member->displayName() }}</span>
                        @if ($district)
                            <span class="ml-2 text-xs text-slate-500">{{ $district }}</span>
                        @endif
                    </span>
                    <span class="flex items-center gap-2 text-sm">
                        <span class="text-slate-500">Present</span>
                        <input type="hidden" name="presence[{{ $member->id }}]" value="0">
                        <input type="checkbox" name="presence[{{ $member->id }}]" value="1" @checked(old('presence.'.$member->id, $attendance?->is_present)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    </span>
                </label>
            @endforeach
        </div>

        <div class="pt-2">
            <button type="submit" class="splis-btn-primary">Save attendance</button>
        </div>
    </form>
</div>
@endsection
