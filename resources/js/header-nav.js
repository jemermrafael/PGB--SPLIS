export function initHeaderNav() {
    const toggle = document.getElementById('splis-nav-toggle');
    const nav = document.getElementById('splis-main-nav');

    if (! toggle || ! nav) {
        return;
    }

    const close = () => {
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        const open = nav.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    nav.addEventListener('click', (event) => {
        if (event.target.closest('a[href]')) {
            close();
        }
    });

    document.addEventListener('click', (event) => {
        if (! nav.classList.contains('is-open')) {
            return;
        }

        if (nav.contains(event.target) || toggle.contains(event.target)) {
            return;
        }

        close();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });

    window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
        if (event.matches) {
            close();
        }
    });
}
