export function initDropdowns(selector = '[data-dropdown]') {
    const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    document.querySelectorAll(selector).forEach((wrap) => {
        const trigger = wrap.querySelector('[data-dropdown-trigger]');
        const panel = wrap.querySelector('[data-dropdown-panel]');

        if (!trigger || !panel) {
            return;
        }

        let closeTimer = null;

        const open = () => {
            clearTimeout(closeTimer);
            closeAllDropdowns(wrap);
            panel.classList.add('open');
            trigger.setAttribute('aria-expanded', 'true');
        };

        const close = () => {
            clearTimeout(closeTimer);
            panel.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
        };

        const scheduleClose = () => {
            closeTimer = setTimeout(close, 150);
        };

        wrap.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            if (panel.classList.contains('open')) {
                close();
            } else {
                open();
            }
        });

        if (canHover) {
            wrap.addEventListener('mouseenter', open);
            wrap.addEventListener('mouseleave', scheduleClose);
        }
    });

    document.addEventListener('click', () => {
        closeAllDropdowns();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllDropdowns();
        }
    });
}

function closeAllDropdowns(except = null) {
    document.querySelectorAll('[data-dropdown]').forEach((wrap) => {
        if (wrap === except) {
            return;
        }

        const panel = wrap.querySelector('[data-dropdown-panel]');
        const trigger = wrap.querySelector('[data-dropdown-trigger]');

        panel?.classList.remove('open');
        trigger?.setAttribute('aria-expanded', 'false');
    });
}
