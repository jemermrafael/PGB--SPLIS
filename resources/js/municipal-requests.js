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

function renderStatusBadge(status, statusLabel) {
    const label = escapeHtml(statusLabel || status || '—');
    const statusClass = status ? ` splis-agenda-status--${escapeHtml(status)}` : '';

    return `<span class="splis-agenda-status${statusClass}">${label}</span>`;
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

function updateStats(stats) {
    if (!stats) {
        return;
    }

    const map = {
        'municipal-stat-pending': stats.pending,
        'municipal-stat-expiring-soon': stats.expiring_soon,
        'municipal-stat-due-soon': stats.due_soon,
        'municipal-stat-done': stats.done,
        'municipal-stat-lapsed': stats.lapsed,
    };

    Object.entries(map).forEach(([id, value]) => {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = Number(value || 0).toLocaleString();
        }
    });
}

function setActiveChip(root, activeButton) {
    root.querySelectorAll('[data-municipal-stat-filter]').forEach((button) => {
        button.classList.toggle('splis-stat--active', button === activeButton);
    });
}

function initMunicipalSearch(root, { compact = false } = {}) {
    if (!root) {
        return;
    }

    const form = root.querySelector('form[id$="-search-form"]');
    const results = root.querySelector('[id$="-search-results"]');
    const listBody = root.querySelector('[id$="-list-body"]');
    const meta = root.querySelector('[id$="-search-meta"]');
    const pagination = root.querySelector('[id$="-search-pagination"]');
    const searchUrl = root.dataset.searchUrl;
    const statusSelect = root.querySelector('[id$="-filter-status"], [id$="-request-status"]');
    const dueSoonInput = root.querySelector('#municipal-filter-due-soon');
    const expiringSoonInput = root.querySelector('#municipal-filter-expiring-soon');

    let currentPage = 1;
    let debounceTimer;
    let activeFilterButton = null;

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('expiring_soon') === '1' && expiringSoonInput) {
        expiringSoonInput.value = '1';
    }

    fetchResults();

    if (urlParams.get('expiring_soon') === '1') {
        const expiringButton = root.querySelector('[data-filter-expiring-soon="1"]');
        if (expiringButton) {
            activeFilterButton = expiringButton;
            setActiveChip(root, expiringButton);
        }
    }

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        currentPage = 1;
        fetchResults();
    });

    form?.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            currentPage = 1;
            activeFilterButton = null;
            setActiveChip(root, null);
            fetchResults();
        }, 400);
    });

    form?.addEventListener('change', () => {
        currentPage = 1;
        activeFilterButton = null;
        setActiveChip(root, null);
        fetchResults();
    });

    form?.addEventListener('reset', () => {
        setTimeout(() => {
            currentPage = 1;
            activeFilterButton = null;
            setActiveChip(root, null);
            if (dueSoonInput) {
                dueSoonInput.value = '';
            }
            if (expiringSoonInput) {
                expiringSoonInput.value = '';
            }
            fetchResults();
        }, 0);
    });

    root.querySelectorAll('[data-municipal-stat-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            if (dueSoonInput) {
                dueSoonInput.value = '';
            }
            if (expiringSoonInput) {
                expiringSoonInput.value = '';
            }
            if (statusSelect) {
                statusSelect.value = '';
            }

            if (button.dataset.filterStatus && statusSelect) {
                statusSelect.value = button.dataset.filterStatus;
            }
            if (button.dataset.filterDueSoon && dueSoonInput) {
                dueSoonInput.value = '1';
            }
            if (button.dataset.filterExpiringSoon && expiringSoonInput) {
                expiringSoonInput.value = '1';
            }

            activeFilterButton = button;
            setActiveChip(root, activeFilterButton);
            currentPage = 1;
            fetchResults();
        });
    });

    listBody?.addEventListener('click', (event) => {
        const row = event.target.closest('.splis-agenda-row');
        if (!row || event.target.closest('a')) {
            return;
        }

        const href = row.dataset.href;
        if (href) {
            window.location.href = href;
        }
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
            meta.textContent = 'Unable to load requests.';
            listBody.innerHTML = '';
            pagination.innerHTML = '';
        } finally {
            results.classList.remove('opacity-60');
        }
    }

    function renderResults(payload) {
        const items = payload.data || [];
        const { total, current_page: page, last_page: lastPage } = payload.meta || {};
        const colSpan = compact ? 6 : 8;

        meta.textContent = `${Number(total || 0).toLocaleString()} Request(s) found`;
        updateStats(payload.stats);

        if (items.length === 0) {
            listBody.innerHTML = `<tr><td colspan="${colSpan}" class="py-12 text-center text-slate-400">No requests match your filters.</td></tr>`;
            pagination.innerHTML = '';
            return;
        }

        listBody.innerHTML = items.map((item) => {
            if (compact) {
                return `
                    <tr class="splis-agenda-row" data-href="${escapeHtml(item.url)}">
                        <td class="whitespace-nowrap font-semibold">
                            <a href="${escapeHtml(item.url)}" class="splis-doc-list-link">${escapeHtml(item.list_number ?? item.display_label ?? item.tracking_no ?? 'Unnumbered')}</a>
                        </td>
                        ${renderTitleCell(item.title)}
                        <td class="hidden sm:table-cell whitespace-nowrap">${formatDate(item.date_received)}</td>
                        <td class="whitespace-nowrap">${formatDate(item.due_date)}</td>
                        <td>${renderStatusBadge(item.status, item.status_label)}</td>
                        <td class="whitespace-nowrap"><a href="${escapeHtml(item.url)}" class="splis-link text-sm">View</a></td>
                    </tr>
                `;
            }

            return `
                <tr class="splis-agenda-row" data-href="${escapeHtml(item.url)}">
                    <td class="whitespace-nowrap font-semibold">
                        <a href="${escapeHtml(item.url)}" class="splis-doc-list-link">${escapeHtml(item.list_number ?? item.display_label ?? item.tracking_no ?? 'Unnumbered')}</a>
                    </td>
                    ${renderTitleCell(item.title)}
                    <td class="hidden sm:table-cell whitespace-nowrap">${formatDate(item.date_received)}</td>
                    <td class="whitespace-nowrap">${formatDate(item.due_date)}</td>
                    ${renderDaysLeftCell(item.days_left_label, item.days_left_tone)}
                    <td>${renderStatusBadge(item.status, item.status_label)}</td>
                    <td class="hidden xl:table-cell whitespace-nowrap">${escapeHtml(item.reso_label || '—')}</td>
                </tr>
            `;
        }).join('');

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

export function initMunicipalDashboardSearch() {
    initMunicipalSearch(document.getElementById('municipal-dashboard-search'), { compact: true });
}

export function initMunicipalRequestSearch() {
    initMunicipalSearch(document.getElementById('municipal-request-search'), { compact: false });
}
