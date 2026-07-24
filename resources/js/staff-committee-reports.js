import { renderAjaxPagination } from './pagination';
import { pdfModalTriggerAttrs } from './pdf-embed-url';

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function renderAgendas(agendas) {
    if (!Array.isArray(agendas) || agendas.length === 0) {
        return '—';
    }

    return `<span class="flex flex-wrap gap-x-2 gap-y-1">${agendas.map((agenda) => (
        `<a href="${escapeHtml(agenda.url)}" class="splis-link whitespace-nowrap">${escapeHtml(agenda.label)}</a>`
    )).join('')}</span>`;
}

function renderActions(report) {
    const parts = [
        `<a ${pdfModalTriggerAttrs(report.pdf_url, `${report.title || 'Committee Report'} PDF`)} class="splis-btn-secondary inline-flex items-center gap-2 whitespace-nowrap text-sm">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            View
        </a>`,
    ];

    if (report.can_update && report.edit_url) {
        parts.push(`<a href="${escapeHtml(report.edit_url)}" class="splis-btn-secondary inline-flex items-center gap-2 whitespace-nowrap text-sm">Edit</a>`);
    }

    if (report.can_delete && report.delete_url) {
        parts.push(`
            <form method="POST" action="${escapeHtml(report.delete_url)}" class="inline" data-confirm-submit data-confirm-title="Delete committee report?" data-confirm-message="Delete this uploaded committee report? Tagged agenda PDFs and related session folder copies from this submission will be removed." data-confirm-label="Delete">
                <input type="hidden" name="_token" value="${escapeHtml(csrfToken())}">
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="splis-btn-danger inline-flex items-center gap-2 whitespace-nowrap text-sm">Delete</button>
            </form>
        `);
    }

    return `<div class="inline-flex flex-nowrap items-center justify-end gap-2">${parts.join('')}</div>`;
}

function renderRow(report) {
    return `
        <tr>
            <td class="whitespace-nowrap text-sm">${escapeHtml(report.submitted_at_label)}</td>
            <td class="whitespace-nowrap font-medium">${escapeHtml(report.board_member)}</td>
            <td>
                <div>${escapeHtml(report.title)}</div>
                ${report.filename
                    ? `<div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">${escapeHtml(report.filename)}</div>`
                    : ''}
            </td>
            <td class="text-sm">${renderAgendas(report.agendas)}</td>
            <td class="hidden lg:table-cell whitespace-nowrap text-sm">
                ${escapeHtml(report.submitted_by)}
                ${report.submitted_by_role
                    ? `<span class="mt-0.5 block text-xs text-slate-500">${escapeHtml(report.submitted_by_role)}</span>`
                    : ''}
            </td>
            <td class="whitespace-nowrap text-right">${renderActions(report)}</td>
        </tr>
    `;
}

export function initStaffCommitteeReportsSearch() {
    const root = document.getElementById('staff-committee-reports');
    if (!root) {
        return;
    }

    const form = document.getElementById('staff-cr-search-form');
    const meta = document.getElementById('staff-cr-meta');
    const body = document.getElementById('staff-cr-body');
    const pagination = document.getElementById('staff-cr-pagination');
    const searchUrl = root.dataset.searchUrl;

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
        meta.textContent = 'Searching…';
        body.closest('.splis-table-wrap')?.classList.add('opacity-60');

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
            meta.textContent = 'Unable to load committee reports.';
            body.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-sm text-slate-500">Unable to load committee reports.</td></tr>';
            pagination.innerHTML = '';
        } finally {
            body.closest('.splis-table-wrap')?.classList.remove('opacity-60');
        }
    }

    function renderResults(payload) {
        const items = payload.data || [];
        const { total, current_page: page, last_page: lastPage } = payload.meta || {};

        meta.textContent = `${Number(total || 0).toLocaleString()} Committee Report(s) found`;

        if (items.length === 0) {
            body.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-sm text-slate-500">No committee reports matched your filters.</td></tr>';
            pagination.innerHTML = '';
            return;
        }

        body.innerHTML = items.map(renderRow).join('');
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
