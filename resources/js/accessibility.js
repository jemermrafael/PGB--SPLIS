export function initAccessibility() {
    const root = document.documentElement;
    const trigger = document.getElementById('splis-a11y-trigger');
    const panel = document.getElementById('splis-a11y-panel');

    if (!trigger || !panel) {
        return;
    }

    const savedTheme = localStorage.getItem('splis-theme') || 'light';
    const savedText = localStorage.getItem('splis-text-size') || 'md';

    applyTheme(savedTheme);
    applyTextSize(savedText);
    setActiveButtons('splis-theme-btn', savedTheme);
    setActiveButtons('splis-text-btn', savedText);

    trigger.addEventListener('click', (event) => {
        event.stopPropagation();
        const isOpen = panel.classList.toggle('open');
        trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', (event) => {
        if (!panel.contains(event.target) && !trigger.contains(event.target)) {
            panel.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
        }
    });

    panel.querySelectorAll('[data-theme]').forEach((button) => {
        button.addEventListener('click', () => {
            const theme = button.dataset.theme;
            applyTheme(theme);
            localStorage.setItem('splis-theme', theme);
            setActiveButtons('splis-theme-btn', theme);
        });
    });

    panel.querySelectorAll('[data-text-size]').forEach((button) => {
        button.addEventListener('click', () => {
            const size = button.dataset.textSize;
            applyTextSize(size);
            localStorage.setItem('splis-text-size', size);
            setActiveButtons('splis-text-btn', size);
        });
    });

    function applyTheme(theme) {
        root.classList.remove('dark');
        if (theme === 'dark') {
            root.classList.add('dark');
        }
    }

    function applyTextSize(size) {
        root.classList.remove('text-size-sm', 'text-size-md', 'text-size-lg');
        root.classList.add(`text-size-${size}`);
    }

    function setActiveButtons(groupClass, value) {
        panel.querySelectorAll(`.${groupClass}`).forEach((button) => {
            button.classList.toggle('active', button.dataset.theme === value || button.dataset.textSize === value);
        });
    }
}

const theme = localStorage.getItem('splis-theme');
if (theme === 'dark') {
    document.documentElement.classList.add('dark');
}
const textSize = localStorage.getItem('splis-text-size');
if (textSize) {
    document.documentElement.classList.add(`text-size-${textSize}`);
}
