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
            @php
                $legacyPdfUrl = route('resolutions.pdf', ['series' => $resolution->Series, 'resolutionNo' => $resolution->Resolution_No]);
            @endphp
            @include('partials.pdf-modal-trigger', [
                'url' => $legacyPdfUrl,
                'src' => $legacyPdfUrl,
                'title' => $resolution->Resolution_No.' PDF',
                'label' => 'View PDF',
                'class' => 'splis-btn-primary inline-flex items-center gap-2',
            ])
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
