@extends('layouts.app')

@section('title', 'Session Attendance — '.$session->displayTitle().' — '.config('app.name'))

@section('content')
<div class="max-w-4xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">Session Attendance</h1>
            <p class="splis-page-subtitle">{{ $session->displayTitle() }} · {{ $session->session_date->format('F j, Y') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('ob.sessions.attendance.monthly') }}" class="splis-btn-secondary">Monthly Report</a>
            <a href="{{ route('ob.sessions.show', $session) }}" class="splis-btn-secondary">Back to Session</a>
        </div>
    </div>

    @if (session('status'))
        <div class="splis-alert-success mb-6">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('ob.sessions.attendance.update', $session) }}" class="splis-card splis-card-body space-y-4">
        @csrf
        @method('PUT')

        <p class="text-sm text-slate-600 dark:text-slate-400">Mark Vice Governor and Board Members present for this session.</p>

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

        <div class="border-t border-slate-200 pt-4 dark:border-slate-700">
            <p class="splis-detail-label mb-3">Guests</p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="splis-label" for="guest_name">Name</label>
                    <input
                        type="text"
                        name="guest_name"
                        id="guest_name"
                        class="splis-input"
                        value="{{ old('guest_name', $session->guest_name) }}"
                        placeholder="Guest name"
                    >
                    @error('guest_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="splis-label" for="guest_remarks">Remarks</label>
                    <input
                        type="text"
                        name="guest_remarks"
                        id="guest_remarks"
                        class="splis-input"
                        value="{{ old('guest_remarks', $session->guest_remarks) }}"
                        placeholder="Optional remarks"
                    >
                    @error('guest_remarks')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" class="splis-btn-primary">Save Attendance</button>
        </div>
    </form>
</div>
@endsection
