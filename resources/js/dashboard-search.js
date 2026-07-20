import { renderAjaxPagination } from './pagination';
import { bindTitleTooltips, renderTruncatedTitle, truncateWords } from './title-tooltip';
import { preferredDocView } from './doc-view';
import { pdfModalTriggerAttrs } from './pdf-embed-url';

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

function renderPublicationStatusButton(doc, compact = true) {
    if (!doc.publication_status_label) {
        return '—';
    }

    const compactClass = compact ? ' splis-ordinance-status-btn--compact' : '';
    const btnClass = `${doc.publication_status_button_class || ''}${compactClass}`.trim();
    const iconSize = compact ? 16 : 20;
    const icon = doc.publication_status_icon_url
        ? `<img src="${escapeHtml(doc.publication_status_icon_url)}" alt="" class="splis-ordinance-status-btn-icon" width="${iconSize}" height="${iconSize}">`
        : '';

    return `<span class="${escapeHtml(btnClass)}" role="status">${icon}<span>${escapeHtml(doc.publication_status_label)}</span></span>`;
}

function renderPublicationCell(doc) {
    if (doc.document_type !== 'ordinance') {
        return '<td class="hidden xl:table-cell">—</td>';
    }

    return `<td class="hidden xl:table-cell whitespace-nowrap">${renderPublicationStatusButton(doc)}</td>`;
}

function renderTitleCell(title) {
    const { display, full, truncated } = truncateWords(title);

    return `<td class="splis-table-title splis-table-title--list">${renderTruncatedTitle(display, full, truncated)}</td>`;
}

function renderListItem(doc) {
    const seriesLine = doc.series_label
        ? `<span class="mt-0.5 block text-xs font-normal text-slate-500 dark:text-slate-400">${escapeHtml(doc.series_label)}</span>`
        : (doc.series
            ? `<span class="mt-0.5 block text-xs font-normal text-slate-500 dark:text-slate-400">Series of ${escapeHtml(doc.series)}</span>`
            : '');

    return `
        <tr>
            <td class="whitespace-nowrap font-semibold">
                <a href="${escapeHtml(doc.url)}" class="splis-doc-list-link font-semibold">${escapeHtml(doc.number)}</a>
                ${seriesLine}
            </td>
            <td class="hidden sm:table-cell">
                <span class="${escapeHtml(doc.document_type_badge_class || 'splis-badge-doc-type splis-badge-doc-type--resolution')}">${escapeHtml(doc.document_type_label || 'Resolution')}</span>
            </td>
            ${renderTitleCell(doc.title)}
            <td class="hidden md:table-cell">${escapeHtml(doc.author || '—')}</td>
            <td class="hidden lg:table-cell">${escapeHtml(doc.committee || '—')}</td>
            <td class="hidden sm:table-cell whitespace-nowrap">${formatDate(doc.date)}</td>
            ${renderPublicationCell(doc)}
            <td><span class="splis-badge-approved capitalize">${escapeHtml(doc.status || '—')}</span></td>
            <td class="text-center">
                ${doc.has_pdf
                    ? `<a ${pdfModalTriggerAttrs(doc.pdf_url, `${doc.number || 'Document'} PDF`)} class="splis-doc-pdf-icon" title="View PDF" aria-label="View PDF">${pdfListIcon}</a>`
                    : '<span class="text-slate-300">—</span>'}
            </td>
        </tr>
    `;
}

function renderGridItem(doc) {
    const { display, full, truncated } = truncateWords(doc.title);

    return `
        <article class="splis-doc-card flex flex-col gap-3">
            <div class="flex items-start justify-between gap-2">
                <a href="${escapeHtml(doc.url)}" class="splis-doc-card-number">${escapeHtml(doc.number)}</a>
                <div class="flex shrink-0 flex-col items-end gap-2">
                    <span class="${escapeHtml(doc.document_type_badge_class || 'splis-badge-doc-type splis-badge-doc-type--resolution')}">${escapeHtml(doc.document_type_label || 'Resolution')}</span>
                    ${doc.document_type === 'ordinance' && doc.publication_status_label ? renderPublicationStatusButton(doc) : ''}
                </div>
            </div>
            <p class="splis-doc-card-title">${renderTruncatedTitle(display, full, truncated)}</p>
            <dl class="splis-doc-card-meta">
                <div><dt>Author</dt><dd>${escapeHtml(doc.author || '—')}</dd></div>
                <div><dt>Date</dt><dd>${formatDate(doc.date)}</dd></div>
                <div class="col-span-2"><dt>Committee</dt><dd>${escapeHtml(doc.committee || '—')}</dd></div>
            </dl>
            <div class="mt-auto flex items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-700">
                <span class="splis-badge-approved capitalize">${escapeHtml(doc.status || '—')}</span>
                ${doc.has_pdf
                    ? `<a ${pdfModalTriggerAttrs(doc.pdf_url, `${doc.number || 'Document'} PDF`)} class="splis-doc-list-link text-xs font-semibold">View PDF</a>`
                    : ''}
            </div>
        </article>
    `;
}

const ADVANCED_FILTER_FIELDS = [
    'author',
    'committee',
    'keyword',
    'date_from',
    'date_to',
    'status',
    'has_pdf',
    'category_id',
    'department_id',
    'municipality_id',
];

function advancedFiltersActive(form) {
    return ADVANCED_FILTER_FIELDS.some((name) => {
        const field = form.elements.namedItem(name);
        if (!field) {
            return false;
        }

        if (field instanceof RadioNodeList) {
            return Array.from(field).some((input) => input.type === 'checkbox' ? input.checked : String(input.value).trim() !== '');
        }

        if (field.type === 'checkbox') {
            return field.checked;
        }

        return String(field.value).trim() !== '';
    });
}

function syncAdvancedFiltersPanel(form) {
    const advanced = document.getElementById('dashboard-advanced-filters');
    if (!advanced) {
        return;
    }

    if (advancedFiltersActive(form)) {
        advanced.open = true;
    }
}

export function initDashboardSearch() {
    const root = document.getElementById('dashboard-search');
    if (!root) {
        return;
    }

    const form = document.getElementById('dashboard-search-form');
    const results = document.getElementById('dashboard-search-results');
    const listBody = document.getElementById('dashboard-list-body');
    const grid = document.getElementById('dashboard-grid');
    const listWrap = document.getElementById('dashboard-list-wrap');
    const meta = document.getElementById('dashboard-search-meta');
    const pagination = document.getElementById('dashboard-search-pagination');
    const viewToggle = document.getElementById('dashboard-view-toggle');
    const searchUrl = root.dataset.searchUrl;

    let currentPage = 1;
    let viewMode = preferredDocView('splis-doc-view');
    let debounceTimer;

    setViewMode(viewMode);
    syncAdvancedFiltersPanel(form);
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
            const advanced = document.getElementById('dashboard-advanced-filters');
            if (advanced) {
                advanced.open = false;
            }
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
            meta.textContent = 'Unable to load documents.';
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

        meta.textContent = `${Number(total || 0).toLocaleString()} Document(s) found`;

        if (docs.length === 0) {
            listBody.innerHTML = '<tr><td colspan="9" class="py-12 text-center text-slate-400">No documents match your filters.</td></tr>';
            grid.innerHTML = '<p class="col-span-full py-12 text-center text-slate-400">No documents match your filters.</p>';
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
