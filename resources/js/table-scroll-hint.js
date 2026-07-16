/**
 * Show a "scroll sideways" hint when a table wrapper overflows horizontally.
 *
 * @param {ParentNode} [root=document]
 */
export function initTableScrollHints(root = document) {
    root.querySelectorAll('.splis-table-wrap').forEach((wrap) => bindTableScrollHint(wrap));
}

/**
 * Re-check overflow on all (or scoped) table wraps — call after AJAX list renders.
 *
 * @param {ParentNode} [root=document]
 */
export function refreshTableScrollHints(root = document) {
    root.querySelectorAll('.splis-table-wrap').forEach((wrap) => {
        if (!(wrap instanceof HTMLElement)) {
            return;
        }

        bindTableScrollHint(wrap);
        updateOverflowState(wrap);
    });
}

/**
 * @param {Element} wrap
 */
function bindTableScrollHint(wrap) {
    if (!(wrap instanceof HTMLElement) || wrap.dataset.scrollHintBound === '1') {
        return;
    }

    wrap.dataset.scrollHintBound = '1';

    if (! wrap.querySelector('.splis-table-scroll-hint')) {
        const hint = document.createElement('p');
        hint.className = 'splis-table-scroll-hint';
        hint.setAttribute('aria-hidden', 'true');
        hint.textContent = 'Swipe sideways to see more columns';
        wrap.prepend(hint);
    }

    const update = () => updateOverflowState(wrap);

    update();

    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(update);
        observer.observe(wrap);
        const table = wrap.querySelector('table');
        if (table) {
            observer.observe(table);
        }
    }

    if (typeof MutationObserver !== 'undefined') {
        const mutationObserver = new MutationObserver(update);
        mutationObserver.observe(wrap, { childList: true, subtree: true, characterData: true });
    }

    wrap.addEventListener('scroll', () => {
        wrap.classList.toggle('is-scrolled', wrap.scrollLeft > 8);
    }, { passive: true });

    window.addEventListener('resize', update, { passive: true });
}

/**
 * @param {HTMLElement} wrap
 */
function updateOverflowState(wrap) {
    const overflows = wrap.scrollWidth > wrap.clientWidth + 4;
    wrap.classList.toggle('has-x-overflow', overflows);
    if (! overflows) {
        wrap.classList.remove('is-scrolled');
    }
}
