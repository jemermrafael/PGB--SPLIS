@extends('layouts.app')

@php
    $isEdit = $session->exists;
    $timeValue = old('session_time');
    if ($timeValue === null) {
        $timeValue = $session->formattedSessionTime();
    }
@endphp

@section('title', ($isEdit ? 'Edit Session' : 'New Session').' — Order of Business — '.config('app.name'))

@section('content')
<div class="max-w-3xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ $isEdit ? 'Edit Legislative Session' : 'New legislative session' }}</h1>
            <p class="splis-page-subtitle">
                @if ($isEdit)
                    Session details for the Order of Business document.
                @else
                    Create the session first, then add agenda items in the OB Maker.
                @endif
            </p>
        </div>
    </div>

    <form method="POST" action="{{ $isEdit ? route('ob.sessions.update', $session) : route('ob.sessions.store') }}" class="space-y-6">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="splis-card splis-card-body space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="splis-label" for="session_date">Session date</label>
                    <input type="date" name="session_date" id="session_date" value="{{ old('session_date', $session->session_date?->format('Y-m-d')) }}" class="splis-input" required>
                </div>
                <div>
                    <label class="splis-label" for="session_time">Session time</label>
                    <input type="time" name="session_time" id="session_time" value="{{ $timeValue }}" class="splis-input">
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label" for="session_number">Session number / label</label>
                    <input type="text" name="session_number" id="session_number" value="{{ old('session_number', $session->session_number) }}" class="splis-input" placeholder="25th Regular Session">
                </div>
                <div>
                    <label class="splis-label" for="session_kind">Session kind</label>
                    <select name="session_kind" id="session_kind" class="splis-select" required>
                        @foreach ($sessionKinds as $value => $label)
                            <option value="{{ $value }}" @selected(old('session_kind', $session->session_kind ?: 'regular') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="splis-label" for="status">Status</label>
                    <select name="status" id="status" class="splis-select" required>
                        @foreach ($sessionStatuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $session->status ?: 'draft') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label" for="venue">Venue</label>
                    <input type="text" name="venue" id="venue" value="{{ old('venue', $session->venue) }}" class="splis-input" placeholder="The Bunker, Capitol Compound, Balanga City">
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label" for="prior_session_id">Prior session (for minutes approval)</label>
                    <select name="prior_session_id" id="prior_session_id" class="splis-select">
                        <option value="">— None —</option>
                        @foreach ($priorSessions as $prior)
                            <option value="{{ $prior->id }}" @selected((string) old('prior_session_id', $session->prior_session_id) === (string) $prior->id)>
                                {{ $prior->displayTitle() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label" for="notes">Notes</label>
                    <textarea name="notes" id="notes" rows="3" class="splis-textarea">{{ old('notes', $session->notes) }}</textarea>
                </div>
            </div>
        </div>

        @if ($isEdit)
            <div class="splis-card splis-card-body space-y-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Session Documents (PDF links)</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Google Drive or other PDF links shown on the session page.</p>
                </div>
                <div class="grid grid-cols-1 gap-4">
                    @foreach ($sessionPdfLinks as $field => $label)
                        <div>
                            <label class="splis-label" for="{{ $field }}">{{ $label }}</label>
                            <input
                                type="url"
                                name="{{ $field }}"
                                id="{{ $field }}"
                                value="{{ old($field, $session->{$field}) }}"
                                class="splis-input"
                                placeholder="https://"
                            >
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @unless ($isEdit)
            <div class="splis-card splis-card-body">
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    After creating the session, you will be taken to the OB Maker where you can add agenda items into each section.
                </p>
            </div>
        @endunless

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="splis-btn-primary inline-flex items-center gap-2">
                <x-icon :name="$isEdit ? 'edit' : 'plus'" class="h-4 w-4" :stroke-width="$isEdit ? '1.75' : '2'" />
                {{ $isEdit ? 'Save changes' : 'Create session' }}
            </button>
            <a href="{{ $isEdit ? route('ob.sessions.show', $session) : route('ob.sessions.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Cancel
            </a>
        </div>
    </form>

    @if ($isEdit)
        @can('delete', $session)
            <div class="mt-6 flex justify-end">
                <form method="POST" action="{{ route('ob.sessions.destroy', $session) }}" onsubmit="return confirm('Move this Order of Business session to trash?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="splis-btn-danger inline-flex items-center gap-2">
                        <x-icon name="trash" class="h-4 w-4" />
                        Delete
                    </button>
                </form>
            </div>
        @endcan
    @endif
</div>
@endsection
