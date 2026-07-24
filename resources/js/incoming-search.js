import { renderAjaxPagination } from './pagination';
import { applyKeywordFromQuery } from './search-query';
import { bindTitleTooltips, renderTruncatedTitle, truncateWords } from './title-tooltip';
import {
    escapeHtml,
    renderCommitteeMeta,
    renderDateMeta,
    renderMunicipalityMeta,
    renderStatusBadge,
} from './list-meta';

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

function renderTitleCell(title) {
    const { display, full, truncated } = truncateWords(title);

    return `<td class="splis-table-title splis-table-title--list">${renderTruncatedTitle(display, full, truncated)}</td>`;
}

function renderListItem(item) {
    const fileLabel = item.legacy_file_id ? `#${item.legacy_file_id}` : `#${item.id}`;
    const munLine = item.mun_resolution_no
        ? `<span class="block text-xs font-normal text-slate-500">${escapeHtml(item.mun_resolution_no)}</span>`
        : '';

    return `
        <tr>
            <td class="whitespace-nowrap">
                <a href="${escapeHtml(item.url)}" class="splis-table-title splis-table-title--list font-semibold">
                    ${escapeHtml(fileLabel)}
                    ${munLine}
                </a>
            </td>
            ${renderTitleCell(item.title)}
            <td class="hidden md:table-cell">${renderMunicipalityMeta(item.municipality)}</td>
            <td class="hidden lg:table-cell">${renderCommitteeMeta(item.committee, { key: item.committee_icon_key, url: item.committee_icon_url })}</td>
            <td class="hidden sm:table-cell whitespace-nowrap">${renderDateMeta(formatDate(item.date))}</td>
            <td class="whitespace-nowrap">${escapeHtml(item.sp_number || '—')}</td>
            <td>${renderStatusBadge(item.is_linked ? 'Linked' : 'Unlinked')}</td>
        </tr>
    `;
}

export function initIncomingSearch() {
    const root = document.getElementById('incoming-search');
    if (!root) {
        return;
    }

    const form = document.getElementById('incoming-search-form');
    const results = document.getElementById('incoming-search-results');
    const listBody = document.getElementById('incoming-list-body');
    const meta = document.getElementById('incoming-search-meta');
    const pagination = document.getElementById('incoming-search-pagination');
    const searchUrl = root.dataset.searchUrl;

    let currentPage = 1;
    let debounceTimer;

    applyKeywordFromQuery(form);
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
        }, 400);
    });

    form.addEventListener('change', () => {
        currentPage = 1;
        fetchResults();
    });

    form.addEventListener('reset', () => {
        setTimeout(() => {
            currentPage = 1;
            fetchResults();
        }, 0);
    });

    function buildParams() {
        const data = new FormData(form);
        const params = new URLSearchParams();

        for (const [key, value] of data.entries()) {
            if (String(value).trim() !== '') {
                params.set(key, value);
            }
        }

        params.set('page', String(currentPage));
        return params;
    }

    async function fetchResults() {
        results.classList.add('opacity-60');
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
            meta.textContent = 'Unable to load incoming documents.';
            listBody.innerHTML = '';
            pagination.innerHTML = '';
        } finally {
            results.classList.remove('opacity-60');
        }
    }

    function renderResults(payload) {
        const items = payload.data || [];
        const { total, current_page: page, last_page: lastPage } = payload.meta || {};

        meta.textContent = `${Number(total || 0).toLocaleString()} incoming document(s) found`;

        if (items.length === 0) {
            listBody.innerHTML = '<tr><td colspan="8" class="py-12 text-center text-slate-400">No incoming documents match your filters.</td></tr>';
            pagination.innerHTML = '';
            return;
        }

        listBody.innerHTML = items.map(renderListItem).join('');
        bindTitleTooltips(results);
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
}
