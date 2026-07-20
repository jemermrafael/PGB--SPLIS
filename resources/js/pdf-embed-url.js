/**
 * Convert a stored PDF URL into one suitable for an iframe embed.
 * Mirrors App\Support\PdfEmbedUrl::forIframe().
 */
export function pdfEmbedUrl(url) {
    const value = String(url ?? '').trim();
    if (! value) {
        return '';
    }

    let match = value.match(/drive\.google\.com\/file\/d\/([^/]+)/i);
    if (match) {
        return `https://drive.google.com/file/d/${match[1]}/preview`;
    }

    match = value.match(/drive\.google\.com\/open\?(?:.*&)?id=([^&]+)/i);
    if (match) {
        return `https://drive.google.com/file/d/${encodeURIComponent(decodeURIComponent(match[1]))}/preview`;
    }

    match = value.match(/drive\.google\.com\/uc\?(?:.*&)?id=([^&]+)/i);
    if (match) {
        return `https://drive.google.com/file/d/${encodeURIComponent(decodeURIComponent(match[1]))}/preview`;
    }

    return value;
}

/**
 * Build markup attributes for a PDF modal trigger.
 */
export function pdfModalTriggerAttrs(url, title = 'PDF Document') {
    const href = String(url ?? '').trim();
    if (! href) {
        return '';
    }

    const escape = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');

    const src = pdfEmbedUrl(href);

    return `href="${escape(href)}" data-pdf-modal-open data-pdf-src="${escape(src)}" data-pdf-url="${escape(href)}" data-pdf-title="${escape(title)}"`;
}
