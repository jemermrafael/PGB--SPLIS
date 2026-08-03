import { renderAjaxPagination } from './pagination';
import { escapeHtml } from './list-meta';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function formatArchivedAt(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value.replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) {
        return escapeHtml(value);
    }

    return date.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function truncate(text, max = 80) {
    const value = String(text || 'Untitled');
    if (value.length <= max) {
        return value;
    }

    return `${value.slice(0, max - 1)}…`;
}

function renderRow(item, restoreUrlTemplate) {
    const restoreUrl = restoreUrlTemplate.replace('__ID__', String(item.id));
    const archivedBy = item.archived_by
        ? `<div class="text-xs text-slate-500">${escapeHtml(item.archived_by)}</div>`
        : '';

    return `
        <tr>
            <td>
                <a href="${escapeHtml(item.url)}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">
                    ${escapeHtml(item.display_label)}
                </a>
                <div class="text-xs text-slate-500">${escapeHtml(truncate(item.title || 'Untitled'))}</div>
            </td>
            <td class="hidden md:table-cell text-sm text-slate-600 dark:text-slate-300">
                ${escapeHtml(item.committee || '—')}
            </td>
            <td class="hidden lg:table-cell">
                <span class="splis-agenda-status splis-agenda-status--${escapeHtml(item.status || '')}">${escapeHtml(item.status_label || item.status || '—')}</span>
            </td>
            <td class="whitespace-nowrap text-sm">
                ${formatArchivedAt(item.archived_at)}
                ${archivedBy}
            </td>
            <td class="text-right">
                <div class="inline-flex flex-wrap justify-end gap-2">
                    <a href="${escapeHtml(item.url)}" class="splis-btn-ghost !py-1.5 text-sm">View</a>
                    <form method="POST" action="${escapeHtml(restoreUrl)}">
                        <input type="hidden" name="_token" value="${escapeHtml(csrfToken())}">
                        <button type="submit" class="splis-btn-secondary !py-1.5 text-sm inline-flex items-center gap-1.5">
                            Restore
                        </button>
                    </form>
                </div>
            </td>
        </tr>
    `;
}

export function initArchivesSearch() {
    const root = document.getElementById('archives-search');
    if (!root) {
        return;
    }

    const form = document.getElementById('archives-search-form');
    const results = document.getElementById('archives-search-results');
    const listBody = document.getElementById('archives-list-body');
    const meta = document.getElementById('archives-search-meta');
    const pagination = document.getElementById('archives-search-pagination');
    const countEl = document.getElementById('archives-count');
    const searchUrl = root.dataset.searchUrl;
    const restoreUrlTemplate = root.dataset.restoreUrlTemplate || '';

    let currentPage = 1;
    let debounceTimer;

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        currentPage = 1;
        fetchResults();
    });

    form?.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            currentPage = 1;
            fetchResults();
        }, 350);
    });

    form?.addEventListener('change', () => {
        currentPage = 1;
        fetchResults();
    });

    form?.addEventListener('reset', () => {
        setTimeout(() => {
            currentPage = 1;
            fetchResults();
        }, 0);
    });

    fetchResults();

    async function fetchResults() {
        results?.classList.add('opacity-60');
        if (meta) {
            meta.textContent = 'Searching…';
        }

        const params = new URLSearchParams(new FormData(form));
        [...params.entries()].forEach(([key, value]) => {
            if (String(value).trim() === '') {
                params.delete(key);
            }
        });
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
            const rows = Array.isArray(payload.data) ? payload.data : [];
            const total = Number(payload.meta?.total || 0);

            if (rows.length === 0) {
                listBody.innerHTML = `
                    <tr>
                        <td colspan="5" class="py-8 text-center text-sm text-slate-500">
                            No archived agendas.
                        </td>
                    </tr>
                `;
            } else {
                listBody.innerHTML = rows.map((item) => renderRow(item, restoreUrlTemplate)).join('');
            }

            if (meta) {
                meta.textContent = `${total.toLocaleString()} archived agenda${total === 1 ? '' : 's'}`;
            }

            if (countEl && payload.archived_count !== undefined) {
                countEl.textContent = `(${Number(payload.archived_count || 0).toLocaleString()})`;
            }

            renderAjaxPagination(pagination, {
                page: payload.meta?.current_page || 1,
                lastPage: payload.meta?.last_page || 1,
                onGoToPage: (target) => {
                    currentPage = target;
                    fetchResults();
                    root.scrollIntoView({ behavior: 'smooth', block: 'start' });
                },
            });
        } catch {
            if (meta) {
                meta.textContent = 'Unable to load archived agendas.';
            }
            listBody.innerHTML = '';
            pagination.innerHTML = '';
        } finally {
            results?.classList.remove('opacity-60');
        }
    }
}
