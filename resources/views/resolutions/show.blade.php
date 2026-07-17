@extends('layouts.app')

@section('title', $resolution->resolution_no.' — '.config('app.name'))

@section('content')
@php
    $pdfUrl = $pdfUrl ?? null;
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
        <div class="splis-page-header-actions">
            @can('update', $resolution)
                <a href="{{ route('resolutions.edit', $resolution) }}" class="splis-btn-secondary inline-flex items-center gap-2">
                    <x-icon name="edit" class="h-4 w-4" />
                    Edit
                </a>
            @endcan
            @if ($resolution->trashed())
                @if (auth()->user()?->isSuperadmin())
                    <a href="{{ route('admin.trash.index', ['type' => 'resolutions']) }}" class="splis-btn-ghost inline-flex items-center gap-2">
                        <x-icon name="arrow-left" class="h-4 w-4" />
                        Back to trash
                    </a>
                @else
                    <a href="{{ route('resolutions.index') }}" class="splis-btn-ghost inline-flex items-center gap-2">
                        <x-icon name="arrow-left" class="h-4 w-4" />
                        Back to list
                    </a>
                @endif
            @elseif (auth()->user()?->isMunicipalViewer())
                <a href="{{ route('municipal.requests.index') }}" class="splis-btn-ghost inline-flex items-center gap-2">
                    <x-icon name="arrow-left" class="h-4 w-4" />
                    Back to requests
                </a>
            @else
                <a href="{{ route('resolutions.index') }}" class="splis-btn-ghost inline-flex items-center gap-2">
                    <x-icon name="arrow-left" class="h-4 w-4" />
                    Back to list
                </a>
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
                <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="splis-btn-secondary text-sm inline-flex items-center gap-2">
                    <x-icon name="external-link" class="h-4 w-4" />
                    Open PDF in new tab
                </a>
            </div>
            @if ($hasLocalPdf ?? false)
                <div class="p-4 sm:p-6">
                    <iframe
                        src="{{ $pdfUrl }}"
                        width="100%"
                        class="splis-pdf-embed w-full rounded-xl border border-slate-200 bg-slate-50"
                        title="Resolution {{ $resolution->resolution_no }} PDF"
                    ></iframe>
                </div>
            @else
                <div class="p-4 text-sm text-slate-600 dark:text-slate-400 sm:p-6">
                    PDF is available via external link. Use <strong>Open PDF in new tab</strong> to view it.
                </div>
            @endif
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
                <form
                    method="POST"
                    action="{{ route('resolutions.restore', $resolution) }}"
                    data-confirm-submit
                    data-confirm-title="Restore resolution?"
                    data-confirm-message="Restore this resolution from trash?"
                    data-confirm-label="Restore"
                    data-confirm-danger="0"
                >
                    @csrf
                    <button type="submit" class="splis-btn-secondary inline-flex items-center gap-2">
                        <x-icon name="check-circle" class="h-4 w-4" />
                        Restore resolution
                    </button>
                </form>
            @endcan
            @can('forceDelete', $resolution)
                <form
                    method="POST"
                    action="{{ route('resolutions.force-destroy', $resolution) }}"
                    data-confirm-submit
                    data-confirm-title="Permanently delete resolution?"
                    data-confirm-message="Permanently delete this resolution? This cannot be undone. The PDF file will not be deleted."
                    data-confirm-label="Delete permanently"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="splis-btn-danger inline-flex items-center gap-2">
                        <x-icon name="trash" class="h-4 w-4" />
                        Delete permanently
                    </button>
                </form>
            @endcan
        </div>
    @else
        @can('delete', $resolution)
            <div class="mt-4 flex justify-end">
                <form
                    method="POST"
                    action="{{ route('resolutions.destroy', $resolution) }}"
                    data-confirm-submit
                    data-confirm-title="Move resolution to trash?"
                    data-confirm-message="Move this resolution to trash? Superadmin can restore from Trash."
                    data-confirm-label="Delete"
                >
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
