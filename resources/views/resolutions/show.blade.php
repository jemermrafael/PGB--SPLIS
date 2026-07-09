@extends('layouts.app')

@section('title', $resolution->resolution_no.' — '.config('app.name'))

@section('content')
@php
    $pdfUrl = $hasPdf
        ? route('resolutions.pdf', ['series' => $resolution->series, 'resolutionNo' => $resolution->resolution_no])
        : null;
@endphp

<div class="max-w-5xl">
    @if ($resolution->trashed())
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
            <p class="font-semibold">This resolution is in trash.</p>
            <p class="mt-1">It was removed on {{ $resolution->deleted_at?->format('M d, Y g:i A') }}. Restore it or delete permanently.</p>
        </div>
    @endif

    <div class="splis-page-header !mb-6">
        <div>
            <div class="mb-2 flex items-center gap-2">
                @if ($resolution->legacy_sp_id)
                    <span class="splis-badge-legacy">Imported</span>
                @endif
                @if ($resolution->publishedFromAgenda)
                    <a href="{{ auth()->user()?->isMunicipalViewer() ? route('municipal.requests.show', $resolution->publishedFromAgenda) : route('agenda.show', $resolution->publishedFromAgenda) }}" class="splis-badge-linked">
                        Published from Agenda {{ $resolution->publishedFromAgenda->displayLabel() }} · Series {{ $resolution->publishedFromAgenda->reso_ord_ao_series ?: $resolution->series }}
                    </a>
                @endif
                <span class="splis-badge-approved capitalize">{{ $resolution->status }}</span>
                <span class="text-sm text-slate-500">Series {{ $resolution->series }}</span>
            </div>
            <h1 class="splis-page-title">Resolution No.: {{ $resolution->resolution_no }}</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $resolution)
                <a href="{{ route('resolutions.edit', $resolution) }}" class="splis-btn-secondary">Edit</a>
            @endcan
            @if ($resolution->trashed())
                <a href="{{ route('resolutions.trash') }}" class="splis-btn-secondary">Back to trash</a>
            @elseif (auth()->user()?->isMunicipalViewer())
                <a href="{{ route('municipal.requests.index') }}" class="splis-btn-secondary">Back to requests</a>
            @else
                <a href="{{ route('resolutions.index') }}" class="splis-btn-secondary">Back to list</a>
            @endif
        </div>
    </div>

    <div class="splis-card">
        <div class="splis-card-header">
            <h2 class="splis-card-title">Resolution Details</h2>
        </div>
        <dl>
            @foreach ([
                'Title' => $resolution->resolution_title,
                'Series' => $resolution->series,
                'Date Approved' => $resolution->date_approved?->format('M d, Y'),
                'Sponsored By' => $resolution->sponsored_by,
                'Category' => $resolution->category?->description,
                'Sub-Category 1' => $resolution->category2?->description,
                'Sub-Category 2' => $resolution->category3?->description,
                'Sub-Category 3' => $resolution->category4?->description,
                'Department' => $resolution->department?->description,
                'Municipality' => $resolution->municipality?->description,
                'Province-wide' => $resolution->province ? 'Yes' : 'No',
                'Committee' => $resolution->committee,
                'App/Ord No.' => $resolution->app_ord_no,
                'Amount' => $resolution->amount !== null ? number_format($resolution->amount) : null,
                'Created by' => $resolution->creator?->name,
            ] as $label => $value)
                @if ($value !== null && $value !== '')
                    <div class="splis-detail-row">
                        <dt class="splis-detail-label">{{ $label }}</dt>
                        <dd class="splis-detail-value">{{ $value }}</dd>
                    </div>
                @endif
            @endforeach
            @if ($resolution->keyword)
                <div class="splis-detail-row">
                    <dt class="splis-detail-label">Keyword</dt>
                    <dd class="splis-detail-value">
                        @include('partials.keyword-links', [
                            'value' => $resolution->keyword,
                            'searchUrl' => route('resolutions.index'),
                        ])
                    </dd>
                </div>
            @endif
        </dl>
    </div>

    @if ($pdfUrl)
        <div class="splis-card mt-6">
            <div class="splis-card-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="splis-card-title">PDF Document</h2>
                <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="splis-btn-secondary text-sm">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                    Open PDF in new tab
                </a>
            </div>
            <div class="p-4 sm:p-6">
                <iframe
                    src="{{ $pdfUrl }}"
                    width="100%"
                    class="splis-pdf-embed w-full rounded-xl border border-slate-200 bg-slate-50"
                    title="Resolution {{ $resolution->resolution_no }} PDF"
                ></iframe>
            </div>
        </div>
    @endif

    @if (! $resolution->trashed())
        @include('partials.detail-prev-next', [
            'previous' => $previousResolution,
            'next' => $nextResolution,
            'previousUrl' => $previousResolution ? route('resolutions.show', $previousResolution) : null,
            'nextUrl' => $nextResolution ? route('resolutions.show', $nextResolution) : null,
            'previousLabel' => $previousResolution ? 'Resolution No.: '.$previousResolution->resolution_no : null,
            'nextLabel' => $nextResolution ? 'Resolution No.: '.$nextResolution->resolution_no : null,
            'label' => 'Resolution navigation',
        ])
    @endif

    @if ($resolution->trashed())
        <div class="mt-6 flex flex-wrap justify-end gap-2">
            @can('restore', $resolution)
                <form method="POST" action="{{ route('resolutions.restore', $resolution) }}" onsubmit="return confirm('Restore this resolution?')">
                    @csrf
                    <button type="submit" class="splis-btn-secondary">Restore resolution</button>
                </form>
            @endcan
            @can('forceDelete', $resolution)
                <form method="POST" action="{{ route('resolutions.force-destroy', $resolution) }}" onsubmit="return confirm('Permanently delete this resolution? This cannot be undone. The PDF file will not be deleted.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="splis-btn-danger">Delete permanently</button>
                </form>
            @endcan
        </div>
    @else
        @can('delete', $resolution)
            <div class="mt-4 flex justify-end">
                <form method="POST" action="{{ route('resolutions.destroy', $resolution) }}" onsubmit="return confirm('Move this resolution to trash?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="splis-btn-danger">Move to trash</button>
                </form>
            </div>
        @endcan
    @endif
</div>
@endsection
