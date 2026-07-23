@extends('layouts.app')

@section('title', 'Edit Committee Report — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">Edit Committee Report</h1>
            <p class="splis-page-subtitle">Update the title, replace the PDF, or change tagged agendas. Use Cancel to go back without saving.</p>
        </div>
        <a href="{{ route('board-member.committee-reports.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Back
        </a>
    </div>

    @include('board-member.committee-reports._form', ['report' => $report])

    <div class="mt-6 border-t border-slate-200 pt-6 dark:border-slate-700">
        <form
            method="POST"
            action="{{ route('board-member.committee-reports.destroy', $report) }}"
            data-confirm-submit
            data-confirm-title="Delete committee report?"
            data-confirm-message="Delete this uploaded committee report? Tagged agenda PDFs and related session folder copies from this submission will be removed."
            data-confirm-label="Delete"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="splis-btn-danger inline-flex items-center gap-2">
                <x-icon name="trash" class="h-4 w-4" />
                Delete Report
            </button>
        </form>
    </div>
</div>
@endsection
