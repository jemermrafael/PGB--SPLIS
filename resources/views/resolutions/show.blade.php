@extends('layouts.app')

@section('title', $resolution->resolution_no.' — '.config('app.name'))

@section('content')
@php
    $pdfUrl = $hasPdf
        ? route('resolutions.pdf', ['series' => $resolution->series, 'resolutionNo' => $resolution->resolution_no])
        : null;
@endphp

<div class="max-w-5xl">
    <div class="splis-page-header !mb-6">
        <div>
            <div class="mb-2 flex items-center gap-2">
                @if ($resolution->legacy_sp_id)
                    <span class="splis-badge-legacy">Imported</span>
                @endif
                <span class="splis-badge-approved capitalize">{{ $resolution->status }}</span>
                <span class="text-sm text-slate-500">Series {{ $resolution->series }}</span>
            </div>
            <h1 class="splis-page-title">{{ $resolution->resolution_no }}</h1>
            @if ($resolution->resolution_title)
                <p class="splis-page-subtitle mt-2 text-base leading-relaxed text-slate-700">{{ $resolution->resolution_title }}</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $resolution)
                <a href="{{ route('resolutions.edit', $resolution) }}" class="splis-btn-secondary">Edit</a>
            @endcan
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
                'Keyword' => $resolution->keyword,
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

    <div class="mt-6 flex items-center justify-between">
        <a href="{{ route('resolutions.index') }}" class="splis-btn-ghost">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Back to list
        </a>
        @can('delete', $resolution)
            <form method="POST" action="{{ route('resolutions.destroy', $resolution) }}" onsubmit="return confirm('Delete this resolution? PDF file will not be deleted.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="splis-btn-danger">Delete resolution</button>
            </form>
        @endcan
    </div>
</div>
@endsection
