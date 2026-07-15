import { bindTitleTooltips } from './title-tooltip';
import { renderAjaxPagination } from './pagination';

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function truncateWords(subject, maxWords = 20) {
    const trimmed = String(subject ?? '').trim();
    if (!trimmed) {
        return { display: '—', full: '', truncated: false };
    }

    const words = trimmed.split(/\s+/);
    if (words.length <= maxWords) {
        return { display: trimmed, full: trimmed, truncated: false };
    }

    return {
        display: `${words.slice(0, maxWords).join(' ')}…`,
        full: trimmed,
        truncated: true,
    };
}

function renderRow(record) {
    const title = truncateWords(record.subject);

    return `
        <tr>
            <td class="whitespace-nowrap">
                <a href="${escapeHtml(record.url)}" class="splis-link font-semibold">${escapeHtml(record.number_label)}</a>
                <p class="mt-0.5 text-xs font-normal text-slate-500 dark:text-slate-400">${escapeHtml(record.series_label || `Series of ${record.series_year}`)}</p>
            </td>
            <td class="splis-table-title splis-table-title--list">
                ${title.truncated
                    ? `<span class="splis-title-tip" data-full-title="${escapeHtml(title.full)}" tabindex="0">${escapeHtml(title.display)}</span>`
                    : `<span>${escapeHtml(title.display)}</span>`}
            </td>
            <td class="hidden md:table-cell whitespace-nowrap">${formatDate(record.date_received)}</td>
            <td class="hidden lg:table-cell whitespace-nowrap">${formatDate(record.date_passed)}</td>
            <td class="hidden lg:table-cell whitespace-nowrap">${formatDate(record.date_approved)}</td>
            <td class="hidden xl:table-cell">${escapeHtml(record.authors || '—')}</td>
        </tr>
    `;
}

export function initBoardMemberOrdinancesTable() {
    const tableRoot = document.getElementById('bm-ordinances-table');
    if (!tableRoot) {
        return;
    }

    bindTitleTooltips(tableRoot);

    const pageRoot = document.getElementById('bm-all-ordinances');
    if (!pageRoot) {
        return;
    }

    const form = document.getElementById('bm-ordinances-search-form');
    const meta = document.getElementById('bm-ordinances-meta');
    const body = tableRoot.querySelector('tbody');
    const pagination = document.getElementById('bm-ordinances-pagination');
    const searchUrl = pageRoot.dataset.searchUrl;

    if (!form || !meta || !body || !pagination || !searchUrl) {
        return;
    }

    let currentPage = 1;
    let debounceTimer;

    fetchResults();

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        currentPage = 1;
        fetchResults();
    });

    form.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            currentPage = 1;
            fetchResults();
        }, 350);
    });

    form.addEventListener('change', () => {
        currentPage = 1;
        fetchResults();
    });

    form.addEventListener('reset', () => {
        setTimeout(() => {
            const advanced = document.getElementById('bm-ordinances-advanced-filters');
            if (advanced) {
                advanced.open = false;
            }
            currentPage = 1;
            fetchResults();
        }, 0);
    });

    function buildParams() {
        const formData = new FormData(form);
        const params = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            if (String(value).trim() !== '') {
                params.set(key, value);
            }
        }
        params.set('page', String(currentPage));

        return params;
    }

    async function fetchResults() {
        tableRoot.classList.add('opacity-60');
        meta.textContent = 'Searching…';

        try {
            const response = await fetch(`${searchUrl}?${buildParams().toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Search failed');
            }

            const payload = await response.json();
            renderResults(payload);
        } catch {
            meta.textContent = 'Unable to load ordinances.';
            body.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-sm text-slate-500">Unable to load ordinances.</td></tr>';
            pagination.innerHTML = '';
        } finally {
            tableRoot.classList.remove('opacity-60');
        }
    }

    function renderResults(payload) {
        const items = payload.data || [];
        const { total, current_page: page, last_page: lastPage } = payload.meta || {};

        meta.textContent = `${Number(total || 0).toLocaleString()} Ordinance(s) found`;

        if (items.length === 0) {
            body.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-sm text-slate-500">No ordinances found.</td></tr>';
            pagination.innerHTML = '';
            return;
        }

        body.innerHTML = items.map(renderRow).join('');
        bindTitleTooltips(tableRoot);
        renderAjaxPagination(pagination, {
            page,
            lastPage,
            onGoToPage: (target) => {
                currentPage = target;
                fetchResults();
                pageRoot.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },
        });
    }
}
