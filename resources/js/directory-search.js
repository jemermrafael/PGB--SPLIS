import { renderAjaxPagination } from './pagination';

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
    const category = root.dataset.category || '';

    let currentPage = Number(root.dataset.currentPage) || 1;
    let debounceTimer;

    const form = document.getElementById('directory-search-form');

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
        if (category !== '') {
            params.set('category', category);
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
        } catch {
            if (meta) {
                meta.textContent = 'Unable to load directory entries.';
            }
            listBody.innerHTML = '';
            pagination.innerHTML = '';
        } finally {
            results?.classList.remove('opacity-60');
        }
    }
}
