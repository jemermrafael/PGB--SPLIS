@extends('layouts.app')

@section('title', 'Submit Committee Report — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">Submit Committee Report</h1>
            <p class="splis-page-subtitle">Upload a PDF and tag Agendas from your Committee Chairmanship that still need a report.</p>
        </div>
        <a href="{{ route('board-member.committee-reports.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Back
        </a>
    </div>

    @include('board-member.committee-reports._form')
</div>
@endsection
