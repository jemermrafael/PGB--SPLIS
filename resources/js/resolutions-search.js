import { renderAjaxPagination } from './pagination';
import { applyKeywordFromQuery } from './search-query';
import { bindTitleTooltips, renderTruncatedTitle, truncateWords } from './title-tooltip';

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

const pdfListIcon = `<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>`;

function renderTitleCell(title) {
    const { display, full, truncated } = truncateWords(title);

    return `<td class="splis-table-title splis-table-title--list">${renderTruncatedTitle(display, full, truncated)}</td>`;
}

function renderPdfCell(doc) {
    return `<td class="text-center">
        ${doc.has_pdf
            ? `<a href="${escapeHtml(doc.pdf_url)}" target="_blank" rel="noopener noreferrer" class="splis-doc-pdf-icon" title="View PDF" aria-label="View PDF">${pdfListIcon}</a>`
            : '<span class="text-slate-300">—</span>'}
    </td>`;
}

function renderListItem(doc) {
    return `
        <tr>
            ${renderPdfCell(doc)}
            <td class="whitespace-nowrap font-semibold">
                <a href="${escapeHtml(doc.url)}" class="splis-doc-list-link">${escapeHtml(doc.number)}</a>
            </td>
            ${renderTitleCell(doc.title)}
            <td class="hidden md:table-cell">${escapeHtml(doc.author || '—')}</td>
            <td class="hidden lg:table-cell">${escapeHtml(doc.committee || '—')}</td>
            <td class="hidden sm:table-cell whitespace-nowrap">${formatDate(doc.date)}</td>
            <td class="hidden xl:table-cell">${escapeHtml(doc.category || '—')}</td>
            <td><span class="splis-badge-approved capitalize">${escapeHtml(doc.status || '—')}</span></td>
            <td class="hidden lg:table-cell text-slate-500">${escapeHtml(doc.series || '—')}</td>
        </tr>
    `;
}

function renderGridItem(doc) {
    const { display, full, truncated } = truncateWords(doc.title);

    return `
        <article class="splis-doc-card flex flex-col gap-3">
            <div class="flex items-start justify-between gap-2">
                <a href="${escapeHtml(doc.url)}" class="splis-doc-card-number">${escapeHtml(doc.number)}</a>
                <span class="text-xs font-medium text-slate-500">${escapeHtml(doc.series || '—')}</span>
            </div>
            <p class="splis-doc-card-title">${renderTruncatedTitle(display, full, truncated)}</p>
            <dl class="splis-doc-card-meta">
                <div><dt>Author</dt><dd>${escapeHtml(doc.author || '—')}</dd></div>
                <div><dt>Date</dt><dd>${formatDate(doc.date)}</dd></div>
                <div class="col-span-2"><dt>Committee</dt><dd>${escapeHtml(doc.committee || '—')}</dd></div>
                <div class="col-span-2"><dt>Subject</dt><dd>${escapeHtml(doc.category || '—')}</dd></div>
            </dl>
            <div class="mt-auto flex items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-700">
                <span class="splis-badge-approved capitalize">${escapeHtml(doc.status || '—')}</span>
                ${doc.has_pdf
                    ? `<a href="${escapeHtml(doc.pdf_url)}" target="_blank" class="splis-doc-list-link text-xs font-semibold">View PDF</a>`
                    : '<span class="text-xs text-slate-400">No PDF</span>'}
            </div>
        </article>
    `;
}

export function initResolutionsSearch() {
    const root = document.getElementById('resolutions-search');
    if (!root) {
        return;
    }

    const form = document.getElementById('resolutions-search-form');
    const results = document.getElementById('resolutions-search-results');
    const listBody = document.getElementById('resolutions-list-body');
    const grid = document.getElementById('resolutions-grid');
    const listWrap = document.getElementById('resolutions-list-wrap');
    const meta = document.getElementById('resolutions-search-meta');
    const pagination = document.getElementById('resolutions-search-pagination');
    const viewToggle = document.getElementById('resolutions-view-toggle');
    const searchUrl = root.dataset.searchUrl;

    let currentPage = 1;
    let viewMode = localStorage.getItem('splis-doc-view') || 'list';
    let debounceTimer;

    setViewMode(viewMode);
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

    viewToggle?.querySelectorAll('[data-view]').forEach((button) => {
        button.addEventListener('click', () => {
            setViewMode(button.dataset.view);
            localStorage.setItem('splis-doc-view', viewMode);
        });
    });

    function setViewMode(mode) {
        viewMode = mode;
        viewToggle?.querySelectorAll('[data-view]').forEach((button) => {
            const isActive = button.dataset.view === mode;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        listWrap?.classList.toggle('hidden', mode !== 'list');
        grid?.classList.toggle('hidden', mode !== 'grid');
    }

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
            meta.textContent = 'Unable to load resolutions.';
            listBody.innerHTML = '';
            grid.innerHTML = '';
            pagination.innerHTML = '';
        } finally {
            results.classList.remove('opacity-60');
        }
    }

    function renderResults(payload) {
        const docs = payload.data || [];
        const { total, current_page: page, last_page: lastPage } = payload.meta || {};

        meta.textContent = `${Number(total || 0).toLocaleString()} resolution(s) found`;

        if (docs.length === 0) {
            listBody.innerHTML = '<tr><td colspan="9" class="py-12 text-center text-slate-400">No resolutions match your filters.</td></tr>';
            grid.innerHTML = '<p class="col-span-full py-12 text-center text-slate-400">No resolutions match your filters.</p>';
            pagination.innerHTML = '';
            return;
        }

        listBody.innerHTML = docs.map(renderListItem).join('');
        grid.innerHTML = docs.map(renderGridItem).join('');
        bindTitleTooltips(results);
        renderPagination(page, lastPage);
    }

    function renderPagination(page, lastPage) {
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
