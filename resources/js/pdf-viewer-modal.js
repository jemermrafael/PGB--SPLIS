import { pdfEmbedUrl } from './pdf-embed-url';

let modal;
let panel;
let frame;
let image;
let titleEl;
let openTab;
let fullscreenBtn;
let bound = false;

function setFullscreen(enabled) {
    if (! modal || ! panel) {
        return;
    }

    modal.classList.toggle('is-fullscreen-active', enabled);
    panel.classList.toggle('is-fullscreen', enabled);

    if (! fullscreenBtn) {
        return;
    }

    fullscreenBtn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
    const label = fullscreenBtn.querySelector('[data-fullscreen-label]');
    const enterIcon = fullscreenBtn.querySelector('[data-fullscreen-icon="enter"]');
    const exitIcon = fullscreenBtn.querySelector('[data-fullscreen-icon="exit"]');

    if (label) {
        label.textContent = enabled ? 'Exit fullscreen' : 'Fullscreen';
    }
    enterIcon?.classList.toggle('hidden', enabled);
    exitIcon?.classList.toggle('hidden', ! enabled);
}

function showViewer(viewer, src) {
    if (! frame || ! image) {
        return;
    }

    if (viewer === 'image') {
        frame.classList.add('hidden');
        frame.setAttribute('src', 'about:blank');
        image.classList.remove('hidden');
        image.src = src;
        image.alt = titleEl?.textContent || 'Document image';
        return;
    }

    image.classList.add('hidden');
    image.removeAttribute('src');
    frame.classList.remove('hidden');
    frame.setAttribute('src', src || 'about:blank');
}

export function openPdfModal({ src = '', url = '', title = 'Document', viewer = 'embed' } = {}) {
    if (! modal || ! panel || ! frame || ! image || ! titleEl || ! openTab) {
        return;
    }

    const openUrl = String(url || src || '').trim();
    const viewerMode = viewer || 'embed';
    const embedSrc = viewerMode === 'embed'
        ? String(src || pdfEmbedUrl(openUrl) || '').trim()
        : String(src || openUrl || '').trim();

    titleEl.textContent = title || 'Document';
    openTab.href = openUrl || '#';
    openTab.hidden = ! openUrl;
    showViewer(viewerMode, embedSrc || 'about:blank');

    setFullscreen(false);
    modal.hidden = false;
    document.body.classList.add('splis-modal-open');
}

export function closePdfModal() {
    if (! modal || ! panel || ! frame || ! image) {
        return;
    }

    setFullscreen(false);
    modal.hidden = true;
    document.body.classList.remove('splis-modal-open');
    frame.setAttribute('src', 'about:blank');
    frame.classList.remove('hidden');
    image.classList.add('hidden');
    image.removeAttribute('src');
}

export function initPdfViewerModal() {
    modal = document.getElementById('splis-pdf-modal');
    panel = modal?.querySelector('[data-pdf-modal-panel]');
    frame = document.getElementById('splis-pdf-modal-frame');
    image = document.getElementById('splis-pdf-modal-image');
    titleEl = document.getElementById('splis-pdf-modal-title');
    openTab = document.getElementById('splis-pdf-modal-open-tab');
    fullscreenBtn = document.getElementById('splis-pdf-modal-fullscreen');

    if (! modal || ! panel || ! frame || ! image || ! titleEl || ! openTab || bound) {
        return;
    }

    bound = true;

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-pdf-modal-open]');
        if (! trigger) {
            return;
        }

        event.preventDefault();

        const url = trigger.dataset.pdfUrl || trigger.getAttribute('href') || '';
        const viewer = trigger.dataset.pdfViewer || 'embed';
        const src = trigger.dataset.pdfSrc || (viewer === 'embed' ? pdfEmbedUrl(url) : url);
        const title = trigger.dataset.pdfTitle || 'Document';

        openPdfModal({ src, url, title, viewer });
    });

    fullscreenBtn?.addEventListener('click', () => {
        setFullscreen(! panel.classList.contains('is-fullscreen'));
    });

    modal.querySelectorAll('[data-pdf-modal-close]').forEach((el) => {
        el.addEventListener('click', closePdfModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || modal.hidden) {
            return;
        }

        if (panel.classList.contains('is-fullscreen')) {
            setFullscreen(false);
            return;
        }

        closePdfModal();
    });
}
