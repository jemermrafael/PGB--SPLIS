import { bindTitleTooltips } from './title-tooltip';
import { renderAjaxPagination } from './pagination';
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

const pdfListIcon = `<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>`;

function renderPdfCell(record) {
    return `<td class="text-center">
        ${record.has_pdf && record.pdf_url
            ? `<a ${pdfModalTriggerAttrs(record.pdf_url, `${record.number_label || 'Resolution'} PDF`)} class="splis-doc-pdf-icon" title="View PDF" aria-label="View PDF">${pdfListIcon}</a>`
            : '<span class="text-slate-300">—</span>'}
    </td>`;
}

function renderRow(record) {
    const title = truncateWords(record.subject);
    const seriesLabel = record.series_label || (record.series_year ? `Series of ${record.series_year}` : '');
    const agendaCell = record.agenda_url && record.agenda_label
        ? `<a href="${escapeHtml(record.agenda_url)}" class="splis-link">${escapeHtml(record.agenda_label)}</a>`
        : '<span class="text-slate-400">—</span>';

    return `
        <tr>
            ${renderPdfCell(record)}
            <td class="whitespace-nowrap">
                <a href="${escapeHtml(record.url)}" class="splis-link font-semibold">${escapeHtml(record.number_label)}</a>
                ${seriesLabel
                    ? `<p class="mt-0.5 text-xs font-normal text-slate-500 dark:text-slate-400">${escapeHtml(seriesLabel)}</p>`
                    : ''}
            </td>
            <td class="splis-table-title splis-table-title--list">
                ${title.truncated
                    ? `<span class="splis-title-tip" data-full-title="${escapeHtml(title.full)}" tabindex="0">${escapeHtml(title.display)}</span>`
                    : `<span>${escapeHtml(title.display)}</span>`}
            </td>
            <td class="hidden md:table-cell whitespace-nowrap">${agendaCell}</td>
            <td class="hidden lg:table-cell">${escapeHtml(record.committee || '—')}</td>
            <td class="hidden lg:table-cell whitespace-nowrap">${formatDate(record.date_approved)}</td>
        </tr>
    `;
}

export function initBoardMemberResolutionsSearch() {
    const pageRoot = document.getElementById('bm-my-resolutions');
    if (!pageRoot) {
        return;
    }

    const form = document.getElementById('bm-resolutions-search-form');
    const meta = document.getElementById('bm-resolutions-meta');
    const tableRoot = document.getElementById('bm-resolutions-table');
    const body = tableRoot?.querySelector('tbody');
    const pagination = document.getElementById('bm-resolutions-pagination');
    const searchUrl = pageRoot.dataset.searchUrl;

    if (!form || !meta || !tableRoot || !body || !pagination || !searchUrl) {
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
            meta.textContent = 'Unable to load resolutions.';
            body.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-sm text-slate-500">Unable to load resolutions.</td></tr>';
            pagination.innerHTML = '';
        } finally {
            tableRoot.classList.remove('opacity-60');
        }
    }

    function renderResults(payload) {
        const items = payload.data || [];
        const { total, current_page: page, last_page: lastPage } = payload.meta || {};

        meta.textContent = `${Number(total || 0).toLocaleString()} Resolution(s) found`;

        if (items.length === 0) {
            body.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-sm text-slate-500">No resolutions connected to agendas you chair.</td></tr>';
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
