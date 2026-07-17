/**
 * Horizontal election-term chip strip with prev/next overflow arrows.
 *
 * @param {ParentNode} [root=document]
 */
export function initTermSwitchers(root = document) {
    root.querySelectorAll('[data-term-switcher]').forEach((switcher) => bindTermSwitcher(switcher));
}

/**
 * @param {Element} switcher
 */
function bindTermSwitcher(switcher) {
    if (!(switcher instanceof HTMLElement) || switcher.dataset.termSwitcherBound === '1') {
        return;
    }

    const track = switcher.querySelector('[data-term-switcher-track]');
    const prev = switcher.querySelector('[data-term-switcher-prev]');
    const next = switcher.querySelector('[data-term-switcher-next]');

    if (!(track instanceof HTMLElement) || !(prev instanceof HTMLButtonElement) || !(next instanceof HTMLButtonElement)) {
        return;
    }

    switcher.dataset.termSwitcherBound = '1';

    const scrollStep = () => Math.max(180, Math.round(track.clientWidth * 0.75));

    const update = () => {
        const maxScroll = track.scrollWidth - track.clientWidth;
        const canScroll = maxScroll > 4;
        const atStart = track.scrollLeft <= 4;
        const atEnd = track.scrollLeft >= maxScroll - 4;

        switcher.classList.toggle('has-overflow', canScroll);
        prev.hidden = ! canScroll || atStart;
        next.hidden = ! canScroll || atEnd;
    };

    prev.addEventListener('click', () => {
        track.scrollBy({ left: -scrollStep(), behavior: 'smooth' });
    });

    next.addEventListener('click', () => {
        track.scrollBy({ left: scrollStep(), behavior: 'smooth' });
    });

    track.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update, { passive: true });

    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(update);
        observer.observe(track);
    }

    const active = track.querySelector('[data-term-switcher-active]');
    if (active instanceof HTMLElement) {
        const left = active.offsetLeft - 8;
        const right = left + active.offsetWidth + 8;

        if (left < track.scrollLeft) {
            track.scrollLeft = Math.max(0, left);
        } else if (right > track.scrollLeft + track.clientWidth) {
            track.scrollLeft = Math.max(0, right - track.clientWidth);
        }
    }

    update();
}
