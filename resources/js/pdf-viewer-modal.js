import { pdfEmbedUrl } from './pdf-embed-url';

let modal;
let panel;
let frame;
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

export function openPdfModal({ src = '', url = '', title = 'Document' } = {}) {
    if (! modal || ! panel || ! frame || ! titleEl || ! openTab) {
        return;
    }

    const openUrl = String(url || src || '').trim();
    const embedSrc = String(src || pdfEmbedUrl(openUrl) || '').trim();

    titleEl.textContent = title || 'Document';
    openTab.href = openUrl || '#';
    openTab.hidden = ! openUrl;
    frame.setAttribute('src', embedSrc || 'about:blank');

    setFullscreen(false);
    modal.hidden = false;
    document.body.classList.add('splis-modal-open');
}

export function closePdfModal() {
    if (! modal || ! panel || ! frame) {
        return;
    }

    setFullscreen(false);
    modal.hidden = true;
    document.body.classList.remove('splis-modal-open');
    frame.setAttribute('src', 'about:blank');
}

export function initPdfViewerModal() {
    modal = document.getElementById('splis-pdf-modal');
    panel = modal?.querySelector('[data-pdf-modal-panel]');
    frame = document.getElementById('splis-pdf-modal-frame');
    titleEl = document.getElementById('splis-pdf-modal-title');
    openTab = document.getElementById('splis-pdf-modal-open-tab');
    fullscreenBtn = document.getElementById('splis-pdf-modal-fullscreen');

    if (! modal || ! panel || ! frame || ! titleEl || ! openTab || bound) {
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
        const src = trigger.dataset.pdfSrc || pdfEmbedUrl(url);
        const title = trigger.dataset.pdfTitle || 'Document';

        openPdfModal({ src, url, title });
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
