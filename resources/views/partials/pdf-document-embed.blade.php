@php
    $pdfUrl = trim((string) ($pdfUrl ?? ''));
    $viewer = $viewer ?? 'embed';
    $embedUrl = \App\Support\PdfEmbedUrl::forIframe($pdfUrl);
    $src = $viewer === 'embed' ? $embedUrl : $pdfUrl;
    $embedTitle = $embedTitle ?? 'PDF Document';
    $buttonLabel = $buttonLabel ?? 'View PDF';
@endphp

@if ($pdfUrl !== '' && $src)
    <div class="splis-card mt-6">
        <div class="splis-card-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="splis-card-title">PDF Document</h2>
            <button
                type="button"
                class="splis-btn-secondary inline-flex items-center gap-2 text-sm"
                data-pdf-modal-open
                data-pdf-viewer="{{ $viewer }}"
                data-pdf-src="{{ $src }}"
                data-pdf-url="{{ $pdfUrl }}"
                data-pdf-title="{{ $embedTitle }}"
            >
                <x-icon name="eye" class="h-4 w-4" />
                {{ $buttonLabel }}
            </button>
        </div>
    </div>
@endif
