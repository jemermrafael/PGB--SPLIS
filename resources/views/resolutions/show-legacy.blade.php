@extends('layouts.app')

@section('title', $resolution->Resolution_No.' — '.config('app.name'))

@section('content')
<div class="max-w-4xl">
    <div class="splis-page-header !mb-6">
        <div>
            <div class="mb-2 flex items-center gap-2">
                <span class="splis-badge-legacy">Legacy record</span>
                <span class="text-sm text-slate-500">Series {{ $resolution->Series }}</span>
            </div>
            <h1 class="splis-page-title">{{ $resolution->Resolution_No }}</h1>
            @if ($resolution->Resolution_Title)
                <p class="splis-page-subtitle mt-2 max-w-2xl">{{ $resolution->Resolution_Title }}</p>
            @endif
        </div>
        @if ($hasPdf)
            <a href="{{ route('resolutions.pdf', ['series' => $resolution->Series, 'resolutionNo' => $resolution->Resolution_No]) }}" target="_blank" class="splis-btn-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                View PDF
            </a>
        @endif
    </div>

    <div class="splis-card">
        <div class="splis-card-header">
            <h2 class="splis-card-title">Resolution Details</h2>
            <p class="mt-0.5 text-xs text-slate-500">Read-only record from the legacy SP Reso archive</p>
        </div>
        <dl>
            @foreach ([
                'Resolution Title' => $resolution->Resolution_Title,
                'Series' => $resolution->Series,
                'Date Approved' => $resolution->Date_App_En,
                'Sponsored By' => $resolution->Sponsored_By,
                'Category' => $labels['category'],
                'Sub-Category 1' => $labels['sub_cat1'],
                'Sub-Category 2' => $labels['sub_cat2'],
                'Sub-Category 3' => $labels['sub_cat3'],
                'Office' => $labels['office'],
                'Municipality' => $labels['municipality'],
                'Province-wide' => $resolution->Province ? 'Yes' : 'No',
                'Keyword' => $resolution->Keyword,
                'Committee' => $resolution->Comittee,
                'App/Ord No.' => $resolution->App_Ord_No,
                'Amount' => $resolution->Amount ? number_format($resolution->Amount) : null,
            ] as $label => $value)
                @if ($value)
                    <div class="splis-detail-row">
                        <dt class="splis-detail-label">{{ $label }}</dt>
                        <dd class="splis-detail-value">{{ $value }}</dd>
                    </div>
                @endif
            @endforeach
        </dl>
    </div>

    <div class="mt-6">
        <a href="{{ route('resolutions.index') }}" class="splis-btn-ghost inline-flex items-center gap-2">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Back to list
        </a>
    </div>
</div>
@endsection
