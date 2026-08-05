import { renderAjaxPagination } from './pagination';
import { initConfirmDialog, showConfirmDialog } from './confirm-dialog';

export function initDirectorySearch() {
    const root = document.getElementById('directory-search');
    if (!root) {
        return;
    }

    const input = document.getElementById('directory-search-input');
    const listBody = document.getElementById('directory-list-body');
    const meta = document.getElementById('directory-search-meta');
    const pagination = document.getElementById('directory-search-pagination');
    const results = document.getElementById('directory-search-results');
    const searchUrl = root.dataset.searchUrl;

    let currentPage = Number(root.dataset.currentPage) || 1;
    let debounceTimer;

    const form = document.getElementById('directory-search-form');

    initDirectoryBulkDelete(root);

    function syncPagination(metaPayload) {
        const page = metaPayload?.current_page || 1;
        const lastPage = metaPayload?.last_page || 1;
        currentPage = page;

        renderAjaxPagination(pagination, {
            page,
            lastPage,
            onGoToPage: (target) => {
                currentPage = target;
                fetchResults();
                root.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },
        });
    }

    syncPagination({
        current_page: currentPage,
        last_page: Number(root.dataset.lastPage) || 1,
    });

    const qFromUrl = new URLSearchParams(window.location.search).get('q');
    if (qFromUrl && input) {
        input.value = qFromUrl.trim();
    }

    if (form && input) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            currentPage = 1;
            fetchResults();
        });

        input.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                currentPage = 1;
                fetchResults();
            }, 350);
        });
    }

    if (input?.value?.trim()) {
        fetchResults();
    }

    async function fetchResults() {
        const q = input?.value?.trim() || '';

        results?.classList.add('opacity-60');
        if (meta) {
            meta.textContent = 'Searching…';
        }

        const params = new URLSearchParams();
        if (q !== '') {
            params.set('q', q);
        }
        params.set('page', String(currentPage));

        try {
            const response = await fetch(`${searchUrl}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Search failed');
            }

            const payload = await response.json();
            listBody.innerHTML = payload.html || '';
            const total = Number(payload.meta?.total || 0);
            if (meta) {
                meta.textContent = q === ''
                    ? `${total.toLocaleString()} entr${total === 1 ? 'y' : 'ies'}`
                    : `${total.toLocaleString()} match${total === 1 ? '' : 'es'} for “${q}”`;
            }
            syncPagination(payload.meta);
            root.dispatchEvent(new CustomEvent('directory:rows-updated'));
        } catch {
            if (meta) {
                meta.textContent = 'Unable to load directory entries.';
            }
            listBody.innerHTML = '';
            pagination.innerHTML = '';
            root.dispatchEvent(new CustomEvent('directory:rows-updated'));
        } finally {
            results?.classList.remove('opacity-60');
        }
    }
}

function initDirectoryBulkDelete(root) {
    const selectAll = root.querySelector('[data-directory-select-all]');
    const checkboxes = () => Array.from(root.querySelectorAll('[data-directory-checkbox]'));
    const bulkForm = root.querySelector('[data-directory-bulk-form]');
    const bulkButton = root.querySelector('[data-directory-bulk-delete]');
    const selectedCount = root.querySelector('[data-directory-selected-count]');

    if (! selectAll && ! bulkForm) {
        return;
    }

    initConfirmDialog();

    function syncSelectionUi() {
        const boxes = checkboxes();
        const selected = boxes.filter((box) => box.checked);
        const total = boxes.length;

        if (selectAll) {
            selectAll.checked = total > 0 && selected.length === total;
            selectAll.indeterminate = selected.length > 0 && selected.length < total;
        }

        if (selectedCount) {
            selectedCount.textContent = selected.length > 0
                ? `${selected.length} selected`
                : 'None selected';
        }

        if (bulkButton) {
            bulkButton.disabled = selected.length === 0;
        }
    }

    selectAll?.addEventListener('change', () => {
        checkboxes().forEach((box) => {
            box.checked = selectAll.checked;
        });
        syncSelectionUi();
    });

    root.addEventListener('change', (event) => {
        if (event.target?.matches?.('[data-directory-checkbox]')) {
            syncSelectionUi();
        }
    });

    root.addEventListener('list-edit:change', () => {
        syncSelectionUi();
    });

    root.addEventListener('directory:rows-updated', () => {
        syncSelectionUi();
    });

    bulkForm?.addEventListener('submit', async (event) => {
        if (bulkForm.dataset.confirmAccepted === '1') {
            delete bulkForm.dataset.confirmAccepted;

            return;
        }

        event.preventDefault();

        const selected = checkboxes().filter((box) => box.checked);

        if (selected.length === 0) {
            return;
        }

        bulkForm.querySelectorAll('input[name="ids[]"]').forEach((input) => input.remove());
        selected.forEach((box) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = box.value;
            bulkForm.appendChild(input);
        });

        const confirmed = await showConfirmDialog({
            title: selected.length === 1 ? 'Delete directory entry?' : `Delete ${selected.length} directory entries?`,
            message: 'Selected entries will be removed. This cannot be undone.',
            confirmLabel: selected.length === 1 ? 'Delete' : `Delete ${selected.length}`,
            danger: true,
        });

        if (! confirmed) {
            return;
        }

        bulkForm.dataset.confirmAccepted = '1';
        bulkForm.requestSubmit();
    });

    syncSelectionUi();
}
