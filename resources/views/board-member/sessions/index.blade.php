@extends('layouts.app')

@section('title', 'My Sessions — '.config('app.name'))

@section('content')
<div class="max-w-6xl">
    <x-page-header
        class="!mb-6"
        title="My Sessions"
        subtitle="Scheduled and completed sessions for your session packet and attendance."
    />

    <div class="splis-card mb-6 overflow-hidden">
        <div class="splis-card-header splis-card-header--emphasis">
            <h2 class="splis-card-title">Upcoming Sessions</h2>
        </div>
        <div class="splis-card-body p-0">
            @if ($upcomingSessions->isEmpty())
                <div class="space-y-2 p-4 text-sm text-slate-500">
                    <p>No upcoming scheduled sessions yet.</p>
                    <p>Draft sessions are hidden here until the SP office sets the session status to <span class="font-medium text-slate-700 dark:text-slate-200">Scheduled</span>.</p>
                </div>
            @else
                <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach ($upcomingSessions as $session)
                        <li class="p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p class="text-sm text-slate-500">{{ $session->session_date?->format('F j, Y') }} · {{ $session->formattedSessionTime() ?: '—' }}</p>
                                    <a href="{{ route('board-member.sessions.show', $session) }}" class="text-base font-semibold text-brand-700 hover:underline dark:text-brand-200">
                                        {{ $session->displayTitle() }}
                                    </a>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $session->venue ?: 'Venue not set' }}</p>
                                    @if ($session->obDocument && ! $session->obDocument->isFinal())
                                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Order of Business is still draft</p>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="splis-badge">{{ $attendanceLabelFor($session) }}</span>
                                    <a href="{{ route('board-member.sessions.show', $session) }}" class="splis-btn-secondary text-sm">Open Session Packet</a>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="splis-card overflow-hidden">
        <div class="splis-card-header splis-card-header--emphasis">
            <h2 class="splis-card-title">Recent Past Sessions</h2>
        </div>
        <div class="splis-card-body p-0">
            @if ($recentPastSessions->isEmpty())
                <p class="p-4 text-sm text-slate-500">No recent past sessions available.</p>
            @else
                <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach ($recentPastSessions as $session)
                        <li class="p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p class="text-sm text-slate-500">{{ $session->session_date?->format('F j, Y') }} · {{ $session->formattedSessionTime() ?: '—' }}</p>
                                    <a href="{{ route('board-member.sessions.show', $session) }}" class="text-base font-semibold text-brand-700 hover:underline dark:text-brand-200">
                                        {{ $session->displayTitle() }}
                                    </a>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $session->venue ?: 'Venue not set' }}</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="splis-badge">{{ $attendanceLabelFor($session) }}</span>
                                    <a href="{{ route('board-member.sessions.show', $session) }}" class="splis-btn-ghost text-sm">View packet</a>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
