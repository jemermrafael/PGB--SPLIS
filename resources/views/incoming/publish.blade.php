@extends('layouts.app')

@section('title', 'Publish to Resolution — '.$incoming->displayLabel().' — '.config('app.name'))

@section('content')
<div class="max-w-3xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">Publish to Resolution</h1>
            <p class="splis-page-subtitle">
                Create a final resolution from incoming
                <span class="font-medium text-slate-700 dark:text-slate-200">{{ $incoming->displayLabel() }}</span>.
                Review and adjust the fields below before saving.
            </p>
        </div>
        <a href="{{ route('incoming.show', $incoming) }}" class="splis-btn-secondary">Back to incoming</a>
    </div>

    <div class="splis-card splis-card-body mb-6 text-sm text-slate-600 dark:text-slate-300">
        <p class="font-semibold text-slate-800 dark:text-slate-100">Pre-filled from incoming</p>
        <ul class="mt-2 list-inside list-disc space-y-1">
            <li>SP Title → Title</li>
            <li>SP Series → Series</li>
            <li>SP Date Approved → Date Approved</li>
            <li>Keyword → Keyword</li>
            <li>Referral → Committee</li>
        </ul>
    </div>

    <form method="POST" action="{{ route('incoming.publish.store', $incoming) }}" enctype="multipart/form-data" class="splis-card splis-card-body space-y-5">
        @csrf

        @include('resolutions._form-fields', [
            'resolution' => $resolution,
            'pdfHint' => $incoming->sp_pdf_url
                ? 'Source PDF URL on file: '.$incoming->sp_pdf_url.' — upload a copy here for the embedded viewer.'
                : null,
        ])

        <div class="flex gap-3 border-t border-slate-100 pt-5 dark:border-slate-700">
            <button type="submit" class="splis-btn-primary">Publish resolution</button>
            <a href="{{ route('incoming.show', $incoming) }}" class="splis-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
