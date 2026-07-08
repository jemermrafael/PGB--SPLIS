@extends('layouts.app')

@section('title', $appropriationOrdinance->displayNumber().' — Appropriation Ordinances — '.config('app.name'))

@section('content')
<div class="max-w-4xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">{{ $appropriationOrdinance->displayNumber() }}</h1>
            <p class="splis-page-subtitle">{{ $appropriationOrdinance->displaySeries() }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($appropriationOrdinance->pdf_url)
                <a href="{{ $appropriationOrdinance->pdf_url }}" target="_blank" rel="noopener" class="splis-btn-secondary">PDF</a>
            @endif
            @can('update', $appropriationOrdinance)
                <a href="{{ route('appropriation-ordinances.edit', $appropriationOrdinance) }}" class="splis-btn-primary">Edit</a>
            @endcan
            <a href="{{ route('appropriation-ordinances.index') }}" class="splis-btn-secondary">Back</a>
        </div>
    </div>

    @if (session('status'))
        <div class="splis-alert-success mb-6">{{ session('status') }}</div>
    @endif

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
                    <dd class="mt-1"><a href="{{ route('agenda.show', $appropriationOrdinance->agenda_item_id) }}" class="splis-link">View agenda item</a></dd>
                </div>
            @endif
        </dl>
    </div>
</div>
@endsection
