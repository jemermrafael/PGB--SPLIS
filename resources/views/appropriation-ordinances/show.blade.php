@extends('layouts.app')

@section('title', $appropriationOrdinance->displayNumber().' — Appropriation Ordinances — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">{{ $appropriationOrdinance->displayNumber() }}</h1>
            <p class="splis-page-subtitle">{{ $appropriationOrdinance->displaySeries() }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $appropriationOrdinance)
                <a href="{{ route('appropriation-ordinances.edit', $appropriationOrdinance) }}" class="splis-btn-primary inline-flex items-center gap-2">
                    <x-icon name="edit" class="h-4 w-4" />
                    Edit
                </a>
                @if ($appropriationOrdinance->needsPdfMirror())
                    <form method="POST" action="{{ route('appropriation-ordinances.mirror-pdf', $appropriationOrdinance) }}">
                        @csrf
                        <button type="submit" class="splis-btn-secondary inline-flex items-center gap-2">
                            <x-icon name="download" class="h-4 w-4" />
                            Download file from Drive
                        </button>
                    </form>
                @endif
            @endcan
            <a href="{{ route('appropriation-ordinances.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Back
            </a>
        </div>
    </div>

    <div class="splis-card splis-card-body space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Title</p>
            <p class="mt-1 whitespace-pre-wrap text-slate-900 dark:text-slate-100">{{ $appropriationOrdinance->subject }}</p>
        </div>

        <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <dt class="splis-label">Date received</dt>
                <dd class="mt-1">{{ $appropriationOrdinance->date_received?->format('F j, Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="splis-label">Date passed by the SP</dt>
                <dd class="mt-1">{{ $appropriationOrdinance->date_passed?->format('F j, Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="splis-label">Date approved by the Governor</dt>
                <dd class="mt-1">{{ $appropriationOrdinance->date_approved?->format('F j, Y') ?? '—' }}</dd>
            </div>
            @if ($appropriationOrdinance->agenda_item_id)
                <div>
                    <dt class="splis-label">Source agenda</dt>
                    <dd class="mt-1"><a href="{{ route('agenda.show', $appropriationOrdinance->agenda_item_id) }}" class="splis-link">View Agenda item</a></dd>
                </div>
            @endif
        </dl>
    </div>

    @include('partials.pdf-document-embed', [
        'pdfUrl' => $appropriationOrdinance->pdfPublicUrl(),
        'viewer' => $appropriationOrdinance->pdfViewerMode(),
        'embedTitle' => $appropriationOrdinance->displayNumber().' PDF',
    ])

    @include('partials.detail-prev-next', [
        'previous' => $previousAppropriationOrdinance ?? null,
        'next' => $nextAppropriationOrdinance ?? null,
        'previousUrl' => ($previousAppropriationOrdinance ?? null) ? route('appropriation-ordinances.show', $previousAppropriationOrdinance) : null,
        'nextUrl' => ($nextAppropriationOrdinance ?? null) ? route('appropriation-ordinances.show', $nextAppropriationOrdinance) : null,
        'previousLabel' => isset($previousAppropriationOrdinance) ? $previousAppropriationOrdinance->displayNumber().' · '.$previousAppropriationOrdinance->displaySeries() : null,
        'nextLabel' => isset($nextAppropriationOrdinance) ? $nextAppropriationOrdinance->displayNumber().' · '.$nextAppropriationOrdinance->displaySeries() : null,
        'label' => 'Appropriation Ordinance navigation',
    ])

    <div class="mt-6 flex flex-wrap gap-2">
        <a href="{{ route('appropriation-ordinances.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Back to list
        </a>
        @can('delete', $appropriationOrdinance)
            <form
                method="POST"
                action="{{ route('appropriation-ordinances.destroy', $appropriationOrdinance) }}"
                data-confirm-submit
                data-confirm-title="Move Appropriation Ordinance to trash?"
                data-confirm-message="Move this Appropriation Ordinance to trash? Superadmin can restore from Trash."
                data-confirm-label="Delete"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="splis-btn-danger inline-flex items-center gap-2">
                    <x-icon name="trash" class="h-4 w-4" />
                    Delete
                </button>
            </form>
        @endcan
    </div>
</div>
@endsection
