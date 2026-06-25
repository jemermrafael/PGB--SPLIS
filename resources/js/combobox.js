export function initComboboxes() {
    document.querySelectorAll('[data-combobox]').forEach((root) => {
        const input = root.querySelector('[data-combobox-input]');
        const trigger = root.querySelector('[data-combobox-trigger]');
        const panel = root.querySelector('[data-combobox-panel]');
        const list = root.querySelector('[data-combobox-list]');

        if (!input || !panel || !list) {
            return;
        }

        let options = [];
        try {
            options = JSON.parse(root.dataset.options || '[]');
        } catch {
            options = [];
        }

        let filtered = [...options];
        let activeIndex = -1;

        function renderOptions(items) {
            filtered = items;
            activeIndex = -1;

            if (!items.length) {
                list.innerHTML = '<p class="splis-combobox-empty">No matching options</p>';
                return;
            }

            list.innerHTML = items.map((item, index) => (
                `<button type="button" class="splis-combobox-option" data-index="${index}" role="option">${escapeHtml(item)}</button>`
            )).join('');

            list.querySelectorAll('.splis-combobox-option').forEach((button) => {
                button.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    selectOption(items[Number(button.dataset.index)]);
                });
            });
        }

        function escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;');
        }

        function filterOptions() {
            const term = input.value.trim().toLowerCase();
            const matches = term === ''
                ? options
                : options.filter((option) => option.toLowerCase().includes(term));

            renderOptions(matches);
        }

        function openPanel() {
            filterOptions();
            panel.classList.add('open');
            root.classList.add('is-open');
        }

        function closePanel() {
            panel.classList.remove('open');
            root.classList.remove('is-open');
            activeIndex = -1;
        }

        function selectOption(value) {
            input.value = value;
            closePanel();
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function highlightOption(index) {
            const buttons = list.querySelectorAll('.splis-combobox-option');
            buttons.forEach((button, i) => {
                button.classList.toggle('is-active', i === index);
            });
            activeIndex = index;
            buttons[index]?.scrollIntoView({ block: 'nearest' });
        }

        trigger?.addEventListener('click', () => {
            if (panel.classList.contains('open')) {
                closePanel();
            } else {
                openPanel();
                input.focus();
            }
        });

        input.addEventListener('focus', openPanel);
        input.addEventListener('input', openPanel);
        input.addEventListener('keydown', (event) => {
            if (!panel.classList.contains('open')) {
                if (event.key === 'ArrowDown' || event.key === 'Enter') {
                    openPanel();
                    event.preventDefault();
                }
                return;
            }

            if (event.key === 'Escape') {
                closePanel();
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                highlightOption(Math.min(activeIndex + 1, filtered.length - 1));
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                highlightOption(Math.max(activeIndex - 1, 0));
                return;
            }

            if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                selectOption(filtered[activeIndex]);
            }
        });

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) {
                closePanel();
            }
        });

        renderOptions(options);
    });
}
