@extends('layouts.app')

@section('title', 'Edit Signatories — '.$monthLabel)

@section('content')
@php
    $preparedBy = $content['prepared_by'] ?? ['name' => '', 'title' => ''];
    $notedBy = $content['noted_by'] ?? ['name' => '', 'title' => ''];
    $approvedBy = $content['approved_by'] ?? ['name' => '', 'title' => ''];
@endphp

<div class="max-w-5xl" id="att-monthly-maker">
    <div class="splis-page-header !mb-4">
        <div class="min-w-0">
            <p class="text-sm text-slate-500">{{ strtoupper($monthLabel) }}</p>
            <h1 class="splis-page-title">Edit Signatories</h1>
            <p class="splis-page-subtitle">Update prepared by, noted by, and approved by for the printable monthly attendance.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('ob.sessions.attendance.monthly.print', ['year' => $year, 'month' => $month]) }}"
                data-pdf-modal-open
                data-pdf-viewer="iframe"
                data-pdf-src="{{ route('ob.sessions.attendance.monthly.print', ['year' => $year, 'month' => $month, 'embed' => 1]) }}"
                data-pdf-url="{{ route('ob.sessions.attendance.monthly.print', ['year' => $year, 'month' => $month]) }}"
                data-pdf-title="Monthly Attendance"
                class="splis-btn-secondary inline-flex items-center gap-2"
            >
                <x-icon name="printer" class="h-4 w-4" />
                Print Preview
            </a>
            <a href="{{ route('ob.sessions.attendance.monthly', ['year' => $year, 'month' => $month]) }}" class="splis-btn-secondary">Monthly Report</a>
        </div>
    </div>

    <p id="att-monthly-save-status" class="mb-4 text-sm text-slate-500" aria-live="polite">Changes save automatically</p>

    <form
        method="POST"
        action="{{ route('ob.sessions.attendance.monthly.update') }}"
        class="space-y-6"
        data-att-monthly-form
    >
        @csrf
        @method('PUT')
        <input type="hidden" name="year" value="{{ $year }}">
        <input type="hidden" name="month" value="{{ $month }}">

        <div class="splis-card splis-card-body grid grid-cols-1 gap-6 sm:grid-cols-3">
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Prepared by</h3>
                <div>
                    <label class="splis-label" for="prepared_by_name">Name</label>
                    <input type="text" name="prepared_by[name]" id="prepared_by_name" class="splis-input" value="{{ old('prepared_by.name', $preparedBy['name'] ?? '') }}">
                </div>
                <div>
                    <label class="splis-label" for="prepared_by_title">Title</label>
                    <input type="text" name="prepared_by[title]" id="prepared_by_title" class="splis-input" value="{{ old('prepared_by.title', $preparedBy['title'] ?? '') }}">
                </div>
            </div>
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Noted by</h3>
                <div>
                    <label class="splis-label" for="noted_by_name">Name</label>
                    <input type="text" name="noted_by[name]" id="noted_by_name" class="splis-input" value="{{ old('noted_by.name', $notedBy['name'] ?? '') }}">
                </div>
                <div>
                    <label class="splis-label" for="noted_by_title">Title</label>
                    <input type="text" name="noted_by[title]" id="noted_by_title" class="splis-input" value="{{ old('noted_by.title', $notedBy['title'] ?? '') }}">
                </div>
            </div>
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Approved by</h3>
                <div>
                    <label class="splis-label" for="approved_by_name">Name</label>
                    <input type="text" name="approved_by[name]" id="approved_by_name" class="splis-input" value="{{ old('approved_by.name', $approvedBy['name'] ?? '') }}">
                </div>
                <div>
                    <label class="splis-label" for="approved_by_title">Title</label>
                    <input type="text" name="approved_by[title]" id="approved_by_title" class="splis-input" value="{{ old('approved_by.title', $approvedBy['title'] ?? '') }}">
                </div>
            </div>
        </div>

        <button type="submit" class="splis-btn-primary inline-flex items-center gap-2">
            <x-icon name="check-circle" class="h-4 w-4" />
            Save now
        </button>
    </form>
</div>
@endsection
