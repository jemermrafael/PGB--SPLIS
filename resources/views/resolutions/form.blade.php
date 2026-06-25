@extends('layouts.app')

@section('title', ($resolution->resolution_no ?: 'New Resolution').' — '.config('app.name'))

@section('content')
<div class="max-w-3xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ isset($resolution) && $resolution->exists ? 'Edit Resolution' : 'New Resolution' }}</h1>
            <p class="splis-page-subtitle">{{ isset($resolution) && $resolution->exists ? 'Update resolution details and attachments.' : 'Enter details for a new legislative record.' }}</p>
        </div>
    </div>

    <form method="POST" action="{{ isset($resolution) && $resolution->exists ? route('resolutions.update', $resolution) : route('resolutions.store') }}" enctype="multipart/form-data" class="splis-card splis-card-body space-y-5">
        @csrf
        @if (isset($resolution) && $resolution->exists)
            @method('PUT')
        @endif

        @include('resolutions._form-fields', ['resolution' => $resolution])

        <div class="flex gap-3 border-t border-slate-100 pt-5 dark:border-slate-700">
            <button type="submit" class="splis-btn-primary">Save resolution</button>
            <a href="{{ route('resolutions.index') }}" class="splis-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
