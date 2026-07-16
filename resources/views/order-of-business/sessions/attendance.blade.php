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
            <a href="{{ route('ob.sessions.attendance.monthly') }}" class="splis-btn-secondary inline-flex items-center gap-2">
                <x-icon name="calendar" class="h-4 w-4" />
                Monthly Report
            </a>
            <a href="{{ route('ob.sessions.show', $session) }}" class="splis-btn-secondary inline-flex items-center gap-2">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Back to Session
            </a>
        </div>
    </div>

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

        <div id="session-guests" class="border-t border-slate-200 pt-4 dark:border-slate-700">
            <div class="mb-3 flex items-center justify-between gap-3">
                <p class="splis-detail-label mb-0">Guests</p>
                <button type="button" class="splis-btn-secondary text-sm" data-guest-add>Add Guest</button>
            </div>

            <div class="space-y-3" data-guest-rows>
                @foreach (old('guests', $session->guestsList()) as $index => $guest)
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_1fr_auto]" data-guest-row>
                        <div>
                            <label class="splis-label">Name</label>
                            <input
                                type="text"
                                name="guests[{{ $index }}][name]"
                                class="splis-input"
                                value="{{ $guest['name'] ?? '' }}"
                                placeholder="Guest name"
                            >
                        </div>
                        <div>
                            <label class="splis-label">Remarks</label>
                            <input
                                type="text"
                                name="guests[{{ $index }}][remarks]"
                                class="splis-input"
                                value="{{ $guest['remarks'] ?? '' }}"
                                placeholder="Optional remarks"
                            >
                        </div>
                        <div class="flex items-end">
                            <button type="button" class="splis-btn-ghost text-sm text-red-600 hover:text-red-700" data-guest-remove>
                                Remove
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            @error('guests')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
            @error('guests.*.name')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
            @error('guests.*.remarks')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <template data-guest-template>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_1fr_auto]" data-guest-row>
                    <div>
                        <label class="splis-label">Name</label>
                        <input type="text" name="guests[__INDEX__][name]" class="splis-input" value="" placeholder="Guest name">
                    </div>
                    <div>
                        <label class="splis-label">Remarks</label>
                        <input type="text" name="guests[__INDEX__][remarks]" class="splis-input" value="" placeholder="Optional remarks">
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="splis-btn-ghost text-sm text-red-600 hover:text-red-700" data-guest-remove>
                            Remove
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <div class="pt-2">
            <button type="submit" class="splis-btn-primary inline-flex items-center gap-2">
                <x-icon name="check-circle" class="h-4 w-4" />
                Save Attendance
            </button>
        </div>
    </form>
</div>
@endsection
