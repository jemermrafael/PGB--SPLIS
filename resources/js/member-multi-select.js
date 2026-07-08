function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function parseOptions(root) {
    try {
        return JSON.parse(root.dataset.options || '[]');
    } catch {
        return [];
    }
}

function parseSelected(root) {
    try {
        return JSON.parse(root.dataset.selected || '[]').map(String);
    } catch {
        return [];
    }
}

function renderChip(label, id) {
    return `
        <span class="splis-member-chip" data-chip-id="${escapeHtml(id)}">
            <span class="splis-member-chip-label">${escapeHtml(label)}</span>
            <button type="button" class="splis-member-chip-remove" data-remove-id="${escapeHtml(id)}" aria-label="Remove ${escapeHtml(label)}">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </span>
    `;
}

function initMemberMultiSelectRoot(root) {
    const fieldName = root.dataset.field;
    const chipsEl = root.querySelector('[data-member-chips]');
    const searchInput = root.querySelector('[data-member-search]');
    const panel = root.querySelector('[data-member-panel]');
    const list = root.querySelector('[data-member-list]');
    const hiddenInputs = root.querySelector('[data-member-hidden]');
    const options = parseOptions(root);

    if (!fieldName || !chipsEl || !searchInput || !panel || !list || !hiddenInputs) {
        return;
    }

    let selected = new Set(parseSelected(root));

    function syncHiddenInputs() {
        hiddenInputs.innerHTML = [...selected]
            .map((id) => `<input type="hidden" name="${escapeHtml(fieldName)}[]" value="${escapeHtml(id)}">`)
            .join('');
    }

    function renderChips() {
        const labels = options
            .filter((option) => selected.has(String(option.id)))
            .map((option) => renderChip(option.label, option.id));

        chipsEl.innerHTML = labels.join('');

        chipsEl.querySelectorAll('[data-remove-id]').forEach((button) => {
            button.addEventListener('click', () => {
                selected.delete(button.dataset.removeId);
                renderChips();
                renderList(searchInput.value);
                syncHiddenInputs();
            });
        });
    }

    function renderList(query = '') {
        const normalized = query.trim().toLowerCase();
        const available = options.filter((option) => {
            if (selected.has(String(option.id))) {
                return false;
            }

            if (normalized === '') {
                return true;
            }

            return option.label.toLowerCase().includes(normalized);
        });

        if (available.length === 0) {
            list.innerHTML = '<p class="splis-member-multi-empty">No matching members</p>';
            return;
        }

        list.innerHTML = available
            .map((option) => `
                <button type="button" class="splis-member-multi-option" data-pick-id="${escapeHtml(option.id)}" role="option">
                    ${escapeHtml(option.label)}
                </button>
            `)
            .join('');

        list.querySelectorAll('[data-pick-id]').forEach((button) => {
            button.addEventListener('click', () => {
                selected.add(button.dataset.pickId);
                searchInput.value = '';
                renderChips();
                renderList();
                syncHiddenInputs();
                searchInput.focus();
            });
        });
    }

    function openPanel() {
        panel.classList.add('open');
        root.classList.add('is-open');
        renderList(searchInput.value);
    }

    function closePanel() {
        panel.classList.remove('open');
        root.classList.remove('is-open');
    }

    searchInput.addEventListener('focus', openPanel);
    searchInput.addEventListener('input', () => renderList(searchInput.value));

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            closePanel();
        }
    });

    renderChips();
    syncHiddenInputs();
}

export function initMemberMultiSelect() {
    document.querySelectorAll('[data-member-multi]').forEach(initMemberMultiSelectRoot);
}

export function initOrdinanceAttributionMode() {
    const root = document.querySelector('[data-ordinance-attribution]');
    if (!root) {
        return;
    }

    const buttons = root.querySelectorAll('[data-attribution-mode]');
    const panels = root.querySelectorAll('[data-attribution-panel]');

    function setMode(mode) {
        buttons.forEach((button) => {
            const isActive = button.dataset.attributionMode === mode;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        panels.forEach((panel) => {
            const isActive = panel.dataset.attributionPanel === mode;
            panel.classList.toggle('hidden', !isActive);
            panel.querySelectorAll('[data-member-hidden] input').forEach((input) => {
                input.disabled = !isActive;
            });
        });
    }

    buttons.forEach((button) => {
        button.addEventListener('click', () => setMode(button.dataset.attributionMode));
    });

    const initialMode = root.dataset.initialMode === 'separate' ? 'separate' : 'combined';
    setMode(initialMode);
}
