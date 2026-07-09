import { renderAjaxPagination } from './pagination';
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

function renderTitleCell(title) {
    const { display, full, truncated } = truncateWords(title);

    return `<td class="splis-table-title splis-table-title--list">${renderTruncatedTitle(display, full, truncated)}</td>`;
}

function renderDaysLeftCell(label, tone) {
    const safeLabel = escapeHtml(label || '—');
    const toneClass = tone ? ` splis-agenda-days--${escapeHtml(tone)}` : '';

    return `<td class="whitespace-nowrap"><span class="splis-agenda-days${toneClass}">${safeLabel}</span></td>`;
}

function renderStatusBadge(status, statusLabel) {
    const label = escapeHtml(statusLabel || status || '—');
    const statusClass = status ? ` splis-agenda-status--${escapeHtml(status)}` : '';

    return `<span class="splis-agenda-status${statusClass}">${label}</span>`;
}

function renderListItem(item) {
    const remarks = String(item.remarks || '').trim();
    return `
        <tr class="splis-agenda-row" data-href="${escapeHtml(item.url)}">
            <td class="whitespace-nowrap font-semibold">
                <a href="${escapeHtml(item.url)}" class="splis-doc-list-link">${escapeHtml(item.tracking_no || item.id)}</a>
            </td>
            ${renderTitleCell(item.title)}
            <td class="hidden md:table-cell">${escapeHtml(item.sender || '—')}</td>
            <td class="hidden lg:table-cell">${escapeHtml(item.committee || '—')}</td>
            <td class="hidden sm:table-cell whitespace-nowrap">${formatDate(item.date_received)}</td>
            <td class="whitespace-nowrap">${formatDate(item.due_date)}</td>
            ${renderDaysLeftCell(item.days_left_label, item.days_left_tone)}
            <td>${renderStatusBadge(item.status, item.status_label)}${item.published_to ? ` <span class="splis-badge-linked ml-1 whitespace-nowrap">Published to ${escapeHtml(item.published_to)}</span>` : ''}</td>
            <td class="hidden xl:table-cell whitespace-nowrap">${escapeHtml(item.reso_label || '—')}</td>
            <td class="text-sm">${escapeHtml(remarks || '—')}</td>
        </tr>
    `;
}

function updateStats(stats) {
    if (!stats) {
        return;
    }

    const map = {
        'agenda-stat-total': stats.total,
        'agenda-stat-pending': stats.pending,
        'agenda-stat-due-soon': stats.due_soon,
        'agenda-stat-done': stats.done,
        'agenda-stat-lapsed': stats.lapsed,
    };

    Object.entries(map).forEach(([id, value]) => {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = Number(value || 0).toLocaleString();
        }
    });
}

function setActiveChip(root, activeButton) {
    root.querySelectorAll('[data-agenda-quick-filter], [data-agenda-stat-filter]').forEach((button) => {
        button.classList.toggle('splis-agenda-chip--active', button === activeButton);
        button.classList.toggle('splis-stat--active', button === activeButton && button.hasAttribute('data-agenda-stat-filter'));
    });
}

export function initAgendaSearch() {
    const root = document.getElementById('agenda-search');
    if (!root) {
        return;
    }

    const form = document.getElementById('agenda-search-form');
    const results = document.getElementById('agenda-search-results');
    const listBody = document.getElementById('agenda-list-body');
    const meta = document.getElementById('agenda-search-meta');
    const pagination = document.getElementById('agenda-search-pagination');
    const searchUrl = root.dataset.searchUrl;
    const statusSelect = document.getElementById('agenda-filter-status');
    const dueSoonInput = document.getElementById('agenda-filter-due-soon');
    const hasIncomingInput = document.getElementById('agenda-filter-has-incoming');

    let currentPage = 1;
    let debounceTimer;
    let activeFilterButton = null;

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
            activeFilterButton = null;
            setActiveChip(root, null);
            fetchResults();
        }, 400);
    });

    form.addEventListener('change', () => {
        currentPage = 1;
        activeFilterButton = null;
        setActiveChip(root, null);
        fetchResults();
    });

    form.addEventListener('reset', () => {
        setTimeout(() => {
            currentPage = 1;
            activeFilterButton = null;
            setActiveChip(root, null);
            if (dueSoonInput) {
                dueSoonInput.value = '';
            }
            if (hasIncomingInput) {
                hasIncomingInput.value = '';
            }
            fetchResults();
        }, 0);
    });

    root.querySelectorAll('[data-agenda-stat-filter], [data-agenda-quick-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            applyQuickFilter(button);
        });
    });

    listBody.addEventListener('click', (event) => {
        const row = event.target.closest('.splis-agenda-row');
        if (!row || event.target.closest('a')) {
            return;
        }

        const href = row.dataset.href;
        if (href) {
            window.location.href = href;
        }
    });

    function applyQuickFilter(button) {
        const isReset = button.hasAttribute('data-filter-reset');

        if (dueSoonInput) {
            dueSoonInput.value = '';
        }
        if (hasIncomingInput) {
            hasIncomingInput.value = '';
        }
        if (statusSelect) {
            statusSelect.value = '';
        }

        if (!isReset) {
            if (button.dataset.filterStatus && statusSelect) {
                statusSelect.value = button.dataset.filterStatus;
            }
            if (button.dataset.filterDueSoon && dueSoonInput) {
                dueSoonInput.value = '1';
            }
            if (button.dataset.filterHasIncoming && hasIncomingInput) {
                hasIncomingInput.value = '1';
            }
        }

        activeFilterButton = isReset ? null : button;
        setActiveChip(root, activeFilterButton);
        currentPage = 1;
        fetchResults();
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
            meta.textContent = 'Unable to load agenda items.';
            listBody.innerHTML = '';
            pagination.innerHTML = '';
        } finally {
            results.classList.remove('opacity-60');
        }
    }

    function renderResults(payload) {
        const items = payload.data || [];
        const { total, current_page: page, last_page: lastPage } = payload.meta || {};

        meta.textContent = `${Number(total || 0).toLocaleString()} agenda item(s) found`;
        updateStats(payload.stats);

        if (items.length === 0) {
            listBody.innerHTML = '<tr><td colspan="10" class="py-12 text-center text-slate-400">No agenda items match your filters.</td></tr>';
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
