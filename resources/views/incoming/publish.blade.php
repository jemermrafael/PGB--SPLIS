@extends('layouts.app')

@section('title', 'Publish to Resolution — '.$incoming->displayLabel().' — '.config('app.name'))

@section('content')
<div class="max-w-3xl">
    <x-page-header
        title="Publish to Resolution"
        :subtitle="'Create a final resolution from '.$incoming->displayLabel().'. Review and adjust the fields below before saving.'"
        class="!mb-6"
    >
        <x-slot:actions>
            <a href="{{ route('incoming.show', $incoming) }}" class="splis-btn-ghost inline-flex items-center gap-2">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Back to incoming
            </a>
        </x-slot:actions>
    </x-page-header>

    @include('incoming.partials.publish-workflow', ['incoming' => $incoming, 'currentStep' => 2])

    <x-help-callout title="What happens next">
        Saving creates a final resolution, links this incoming item to it, and stores the uploaded PDF for the embedded viewer.
    </x-help-callout>

    <x-alert variant="info" class="!mb-6">
        <p class="font-semibold text-slate-800 dark:text-slate-100">Pre-filled from incoming</p>
        <ul class="mt-2 list-inside list-disc space-y-1">
            <li>SP Title → Title</li>
            <li>SP Series → Series</li>
            <li>SP Date Approved → Date Approved</li>
            <li>Keyword → Keyword</li>
            <li>Referral → Committee</li>
        </ul>
    </x-alert>

    <form method="POST" action="{{ route('incoming.publish.store', $incoming) }}" enctype="multipart/form-data" class="splis-card splis-card-body space-y-5">
        @csrf

        @include('resolutions._form-fields', [
            'resolution' => $resolution,
            'pdfHint' => $incoming->sp_pdf_url
                ? 'Source PDF URL on file: '.$incoming->sp_pdf_url.' — upload a copy here for the embedded viewer.'
                : null,
        ])

        <div class="splis-form-actions !mx-0 !mt-2 border-0 bg-transparent p-0 shadow-none dark:bg-transparent">
            <button type="submit" class="splis-btn-primary inline-flex items-center gap-2">
                <x-icon name="file-text" class="h-4 w-4" />
                Publish resolution
            </button>
            <a href="{{ route('incoming.show', $incoming) }}" class="splis-btn-ghost inline-flex items-center gap-2">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
