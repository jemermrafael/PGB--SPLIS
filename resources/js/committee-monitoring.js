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

    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function renderTitleCell(item) {
    const { display, full, truncated } = truncateWords(item.title);

    return `
        <td>
            <a href="${escapeHtml(item.url)}" class="splis-table-title splis-table-title--list">${renderTruncatedTitle(display, full, truncated)}</a>
            <p class="text-xs text-slate-500">${escapeHtml(item.sender || '—')}</p>
        </td>
    `;
}

function renderStatusBadge(status, statusLabel) {
    const label = escapeHtml(statusLabel || status || '—');
    const badgeClass = status === 'completed' ? 'splis-badge-linked' : 'splis-badge-unlinked';

    return `<span class="${badgeClass}">${label}</span>`;
}

function updateStats(stats) {
    if (!stats) {
        return;
    }

    const map = {
        'committee-stat-total': stats.total,
        'committee-stat-pending': stats.pending,
        'committee-stat-scheduled': stats.with_schedule,
        'committee-stat-reports': stats.with_report,
        'committee-stat-completed': stats.completed,
    };

    Object.entries(map).forEach(([id, value]) => {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = Number(value || 0).toLocaleString();
        }
    });
}

function setActiveStat(root, activeButton) {
    root.querySelectorAll('[data-committee-stat-filter]').forEach((button) => {
        button.classList.toggle('splis-stat--active', button === activeButton);
    });
}

export function initCommitteeMonitoring() {
    const root = document.getElementById('committee-monitoring');
    if (!root) {
        return;
    }

    const form = document.getElementById('committee-monitoring-form');
    const results = document.getElementById('committee-monitoring-results');
    const listBody = document.getElementById('committee-monitoring-list-body');
    const meta = document.getElementById('committee-monitoring-meta');
    const pagination = document.getElementById('committee-monitoring-pagination');
    const viewInput = document.getElementById('committee-filter-view');
    const statusSelect = document.getElementById('committee-filter-status');
    const hasReportSelect = document.getElementById('committee-filter-has-report');
    const searchUrl = root.dataset.searchUrl;

    let currentPage = 1;
    let debounceTimer;
    let activeStatButton =
        root.querySelector(`[data-committee-stat-filter][data-view="${viewInput?.value || 'referred'}"]`) ||
        root.querySelector('[data-committee-stat-filter][data-view="referred"]');

    setActiveStat(root, activeStatButton);
    fetchResults();

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        currentPage = 1;
        fetchResults();
    });

    form?.addEventListener('input', (event) => {
        if (event.target?.name !== 'q') {
            return;
        }

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            currentPage = 1;
            fetchResults();
        }, 400);
    });

    form?.addEventListener('change', (event) => {
        if (event.target?.name === 'q') {
            return;
        }

        if (event.target?.name === 'status' || event.target?.name === 'has_report') {
            if (viewInput) {
                viewInput.value = 'referred';
            }
            activeStatButton = root.querySelector('[data-committee-stat-filter][data-view="referred"]');
            setActiveStat(root, activeStatButton);
        }

        currentPage = 1;
        fetchResults();
    });

    form?.addEventListener('reset', () => {
        setTimeout(() => {
            if (viewInput) {
                viewInput.value = 'referred';
            }
            if (statusSelect) {
                statusSelect.value = '';
            }
            if (hasReportSelect) {
                hasReportSelect.value = '';
            }
            activeStatButton = root.querySelector('[data-committee-stat-filter][data-view="referred"]');
            setActiveStat(root, activeStatButton);
            currentPage = 1;
            fetchResults();
        }, 0);
    });

    root.querySelectorAll('[data-committee-stat-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            if (viewInput) {
                viewInput.value = button.dataset.view || 'referred';
            }
            if (statusSelect) {
                statusSelect.value = '';
            }
            if (hasReportSelect) {
                hasReportSelect.value = '';
            }

            activeStatButton = button;
            setActiveStat(root, activeStatButton);
            currentPage = 1;
            fetchResults();
            document.getElementById('committee-queue')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
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

    function syncUrl(params) {
        const next = params.toString();
        const url = next ? `${window.location.pathname}?${next}` : window.location.pathname;
        window.history.replaceState({}, '', url);
    }

    async function fetchResults() {
        results?.classList.add('opacity-60');
        if (meta) {
            meta.textContent = 'Loading…';
        }

        try {
            const params = buildParams();
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
            syncUrl(params);
            renderResults(payload);
        } catch {
            if (meta) {
                meta.textContent = 'Unable to load committee queue.';
            }
            if (listBody) {
                listBody.innerHTML = '';
            }
            if (pagination) {
                pagination.innerHTML = '';
            }
        } finally {
            results?.classList.remove('opacity-60');
        }
    }

    function renderResults(payload) {
        const items = payload.data || [];
        const { total, current_page: page, last_page: lastPage } = payload.meta || {};
        const emptyMessage = payload.empty_message || 'No referred measures found for the selected filters.';

        if (meta) {
            meta.textContent = `${Number(total || 0).toLocaleString()} item(s) found`;
        }

        updateStats(payload.stats);

        const activeView = payload.filters?.view || viewInput?.value || 'referred';
        if (viewInput) {
            viewInput.value = activeView;
        }
        activeStatButton =
            root.querySelector(`[data-committee-stat-filter][data-view="${activeView}"]`) || activeStatButton;
        setActiveStat(root, activeStatButton);

        if (!listBody) {
            return;
        }

        if (items.length === 0) {
            listBody.innerHTML = `<tr><td colspan="8" class="py-10 text-center text-slate-500">${escapeHtml(emptyMessage)}</td></tr>`;
            if (pagination) {
                pagination.innerHTML = '';
            }
            return;
        }

        listBody.innerHTML = items
            .map(
                (item) => `
            <tr>
                <td class="whitespace-nowrap">${escapeHtml(item.display_label || '—')}</td>
                ${renderTitleCell(item)}
                <td class="hidden md:table-cell">${escapeHtml(item.committee || '—')}</td>
                <td class="hidden lg:table-cell whitespace-nowrap">${formatDate(item.date_of_referral)}</td>
                <td class="hidden lg:table-cell whitespace-nowrap">${formatDate(item.date_of_committee_meeting)}</td>
                <td>
                    ${
                        item.has_report && item.committee_report_url
                            ? `<a href="${escapeHtml(item.committee_report_url)}" target="_blank" rel="noopener" class="splis-link">View Report</a>`
                            : '<span class="text-slate-500">—</span>'
                    }
                </td>
                <td>${renderStatusBadge(item.status, item.status_label)}</td>
                <td>${escapeHtml(item.outcome || '—')}</td>
            </tr>
        `,
            )
            .join('');

        bindTitleTooltips(results);
        renderAjaxPagination(pagination, {
            page,
            lastPage,
            onGoToPage: (target) => {
                currentPage = target;
                fetchResults();
                document.getElementById('committee-queue')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },
        });
    }
}
