function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

export function initObSessionForm() {
    const root = document.getElementById('ob-session-create');
    if (!root) {
        return;
    }

    const searchUrl = root.dataset.searchUrl;
    const searchInput = document.getElementById('ob-session-agenda-search');
    const poolEl = document.getElementById('ob-session-agenda-pool');
    const selectedEl = document.getElementById('ob-session-agenda-selected');
    const hiddenContainer = document.getElementById('ob-session-agenda-hidden-inputs');

    const selected = new Map();

    function syncHiddenInputs() {
        if (!hiddenContainer) {
            return;
        }
        hiddenContainer.innerHTML = [...selected.keys()]
            .map((id) => `<input type="hidden" name="agenda_item_ids[]" value="${id}">`)
            .join('');
    }

    function renderSelected() {
        if (!selectedEl) {
            return;
        }
        if (selected.size === 0) {
            selectedEl.innerHTML = '<p class="text-sm text-slate-500">No agenda items selected.</p>';
            return;
        }

        selectedEl.innerHTML = [...selected.values()]
            .map(
                (item) => `
                <div class="splis-ob-session-selected-item">
                    <span>
                        <strong>${escapeHtml(item.label)}</strong>
                        <span class="block text-xs text-slate-500">${escapeHtml(item.title ?? 'Untitled')}</span>
                    </span>
                    <button type="button" class="splis-ob-icon-btn splis-ob-icon-btn--danger" data-remove-id="${item.id}" title="Remove">×</button>
                </div>
            `,
            )
            .join('');
    }

    async function loadPool(query = '') {
        if (!poolEl) {
            return;
        }

        poolEl.innerHTML = '<p class="text-sm text-slate-500">Loading…</p>';

        const params = new URLSearchParams({ page: '1' });
        if (query) {
            if (/^\d+$/.test(query)) {
                params.set('number', query);
            } else {
                params.set('title', query);
            }
        }

        try {
            const response = await fetch(`${searchUrl}?${params.toString()}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();

            if (!data.data?.length) {
                poolEl.innerHTML = '<p class="text-sm text-slate-500">No agenda items found.</p>';
                return;
            }

            poolEl.innerHTML = data.data
                .map((item) => {
                    const label = item.display_label ?? (item.tracking_no ? `#${item.tracking_no}` : 'Pending assignment');
                    const isSelected = selected.has(item.id);
                    return `
                        <label class="splis-ob-agenda-item">
                            <input type="checkbox" value="${item.id}" ${isSelected ? 'checked' : ''}>
                            <span>
                                <strong>${escapeHtml(label)}</strong>
                                <span class="block text-xs text-slate-500">${escapeHtml(item.title ?? 'Untitled')}</span>
                                <span class="block text-xs text-slate-400">${escapeHtml(item.sender ?? '')}</span>
                            </span>
                        </label>
                    `;
                })
                .join('');
        } catch {
            poolEl.innerHTML = '<p class="text-sm text-red-600">Could not load agenda items.</p>';
        }
    }

    root.addEventListener('change', (event) => {
        const target = event.target;
        if (!target.matches('#ob-session-agenda-pool input[type="checkbox"]')) {
            return;
        }

        const id = Number(target.value);
        const labelEl = target.closest('label')?.querySelector('strong');
        const titleEl = target.closest('label')?.querySelector('.text-xs');

        if (target.checked) {
            selected.set(id, {
                id,
                label: labelEl?.textContent ?? 'Pending assignment',
                title: titleEl?.textContent ?? '',
            });
        } else {
            selected.delete(id);
        }

        syncHiddenInputs();
        renderSelected();
    });

    root.addEventListener('click', (event) => {
        const target = event.target;
        if (!target.matches('[data-remove-id]')) {
            return;
        }

        const id = Number(target.dataset.removeId);
        selected.delete(id);
        const checkbox = poolEl?.querySelector(`input[value="${id}"]`);
        if (checkbox) {
            checkbox.checked = false;
        }
        syncHiddenInputs();
        renderSelected();
    });

    if (searchInput) {
        let timer;
        searchInput.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => loadPool(searchInput.value.trim()), 300);
        });
    }

    hiddenContainer?.querySelectorAll('input[name="agenda_item_ids[]"]').forEach((input) => {
        const id = Number(input.value);
        if (id) {
            selected.set(id, { id, label: 'Pending assignment', title: '' });
        }
    });
    syncHiddenInputs();
    renderSelected();

    loadPool();
}

document.addEventListener('DOMContentLoaded', () => {
    initObSessionForm();
});
