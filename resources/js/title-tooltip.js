function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

let tooltipEl;

function getTooltip() {
    if (!tooltipEl) {
        tooltipEl = document.createElement('div');
        tooltipEl.className = 'splis-title-tooltip';
        tooltipEl.setAttribute('role', 'tooltip');
        document.body.appendChild(tooltipEl);
    }

    return tooltipEl;
}

function positionTooltip(trigger, event) {
    const tip = getTooltip();
    const rect = trigger.getBoundingClientRect();
    const tipRect = tip.getBoundingClientRect();
    const gap = 10;

    let top = rect.top - tipRect.height - gap;
    let left = rect.left + rect.width / 2;

    if (top < 8) {
        top = rect.bottom + gap;
        tip.dataset.placement = 'below';
    } else {
        tip.dataset.placement = 'above';
    }

    const half = tipRect.width / 2;
    const margin = 12;
    left = Math.max(margin + half, Math.min(left, window.innerWidth - margin - half));

    tip.style.left = `${left}px`;
    tip.style.top = `${top}px`;
}

function showTooltip(trigger, event) {
    const tip = getTooltip();
    tip.textContent = trigger.dataset.fullTitle || '';
    tip.classList.add('is-visible');
    requestAnimationFrame(() => positionTooltip(trigger, event));
}

function hideTooltip() {
    tooltipEl?.classList.remove('is-visible');
}

export const TITLE_MAX_WORDS = 20;

export function truncateWords(text, maxWords = TITLE_MAX_WORDS) {
    const full = String(text ?? '').trim();
    if (!full) {
        return { display: '—', full: '', truncated: false };
    }

    const words = full.split(/\s+/);
    if (words.length <= maxWords) {
        return { display: full, full, truncated: false };
    }

    return {
        display: `${words.slice(0, maxWords).join(' ')}…`,
        full,
        truncated: true,
    };
}

export function renderTruncatedTitle(display, full, truncated, className = '') {
    if (!truncated) {
        return `<span class="${className}">${escapeHtml(display)}</span>`;
    }

    return `<span class="splis-title-tip ${className}" data-full-title="${escapeHtml(full)}" tabindex="0">${escapeHtml(display)}</span>`;
}

export function bindTitleTooltips(root) {
    if (!root) {
        return;
    }

    root.querySelectorAll('[data-full-title]').forEach((trigger) => {
        if (trigger.dataset.tipBound === '1') {
            return;
        }

        trigger.dataset.tipBound = '1';

        trigger.addEventListener('mouseenter', (event) => showTooltip(trigger, event));
        trigger.addEventListener('mousemove', (event) => {
            if (tooltipEl?.classList.contains('is-visible')) {
                positionTooltip(trigger, event);
            }
        });
        trigger.addEventListener('mouseleave', hideTooltip);
        trigger.addEventListener('focus', (event) => showTooltip(trigger, event));
        trigger.addEventListener('blur', hideTooltip);
    });
}
