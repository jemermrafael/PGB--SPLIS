@php
    $pdfUrl = trim((string) ($pdfUrl ?? ''));
    $embedUrl = \App\Support\PdfEmbedUrl::forIframe($pdfUrl);
    $embedTitle = $embedTitle ?? 'PDF Document';
@endphp

@if ($pdfUrl !== '' && $embedUrl)
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
                src="{{ $embedUrl }}"
                width="100%"
                allow="autoplay"
                class="splis-pdf-embed w-full rounded-xl border border-slate-200 bg-slate-50"
                title="{{ $embedTitle }}"
            ></iframe>
        </div>
    </div>
@endif
