@php
    $url = trim((string) ($url ?? ''));
    $viewer = $viewer ?? 'embed';
    $embedUrl = \App\Support\PdfEmbedUrl::forIframe($url);
    $src = $src ?? ($viewer === 'embed' ? $embedUrl : $url);
    $title = $title ?? 'Document';
    $label = $label ?? 'View PDF';
    $class = $class ?? 'splis-btn-secondary inline-flex items-center gap-2 text-sm';
    $icon = $icon ?? 'eye';
@endphp

@if ($url !== '' && $src)
    <button
        type="button"
        class="{{ $class }}"
        data-pdf-modal-open
        data-pdf-viewer="{{ $viewer }}"
        data-pdf-src="{{ $src }}"
        data-pdf-url="{{ $url }}"
        data-pdf-title="{{ $title }}"
    >
        <x-icon :name="$icon" class="h-4 w-4" />
        {{ $label }}
    </button>
@endif
