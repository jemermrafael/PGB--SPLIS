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

function renderCompactListItem(item) {
    return `
        <tr class="splis-agenda-row" data-href="${escapeHtml(item.url)}">
            <td class="whitespace-nowrap font-semibold">
                <a href="${escapeHtml(item.url)}" class="splis-doc-list-link">${escapeHtml(item.tracking_no || item.id)}</a>
            </td>
            ${renderTitleCell(item.title)}
            <td class="hidden md:table-cell">${escapeHtml(item.committee || '—')}</td>
            <td class="hidden sm:table-cell whitespace-nowrap">${formatDate(item.date_of_referral)}</td>
            <td>${renderStatusBadge(item.status, item.status_label)}</td>
            <td class="whitespace-nowrap"><a href="${escapeHtml(item.url)}" class="splis-link text-sm">View</a></td>
        </tr>
    `;
}

function renderCompactGridItem(item) {
    const { display, full, truncated } = truncateWords(item.title);

    return `
        <article class="splis-doc-card flex flex-col gap-3">
            <div class="flex items-start justify-between gap-2">
                <a href="${escapeHtml(item.url)}" class="splis-doc-card-number">${escapeHtml(item.tracking_no || item.id)}</a>
                ${renderStatusBadge(item.status, item.status_label)}
            </div>
            <p class="splis-doc-card-title">${renderTruncatedTitle(display, full, truncated)}</p>
            <dl class="splis-doc-card-meta">
                <div class="col-span-2"><dt>Committee</dt><dd>${escapeHtml(item.committee || '—')}</dd></div>
                <div><dt>Referred</dt><dd>${formatDate(item.date_of_referral)}</dd></div>
            </dl>
            <div class="mt-auto border-t border-slate-100 pt-3 dark:border-slate-700">
                <a href="${escapeHtml(item.url)}" class="splis-doc-list-link text-xs font-semibold">View agenda</a>
            </div>
        </article>
    `;
}

function renderFullListItem(item) {
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
            <td>${renderStatusBadge(item.status, item.status_label)}</td>
            <td class="hidden xl:table-cell whitespace-nowrap">${escapeHtml(item.reso_label || '—')}</td>
        </tr>
    `;
}

function updateStats(stats) {
    if (!stats) {
        return;
    }

    const map = {
        'bm-agenda-stat-pending': stats.pending,
        'bm-agenda-stat-expiring-soon': stats.expiring_soon,
        'bm-agenda-stat-due-soon': stats.due_soon,
        'bm-agenda-stat-done': stats.done,
        'bm-agenda-stat-lapsed': stats.lapsed,
    };

    Object.entries(map).forEach(([id, value]) => {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = Number(value || 0).toLocaleString();
        }
    });
}

function setActiveChip(root, activeButton) {
    root.querySelectorAll('[data-bm-agenda-stat-filter]').forEach((button) => {
        button.classList.toggle('splis-stat--active', button === activeButton);
    });
}

function initViewToggle(viewToggle, listWrap, grid, storageKey, onChange) {
    let viewMode = localStorage.getItem(storageKey) || 'list';

    const setViewMode = (mode) => {
        viewMode = mode;
        viewToggle?.querySelectorAll('[data-view]').forEach((button) => {
            const isActive = button.dataset.view === mode;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        listWrap?.classList.toggle('hidden', mode !== 'list');
        grid?.classList.toggle('hidden', mode !== 'grid');
        onChange?.(mode);
    };

    viewToggle?.querySelectorAll('[data-view]').forEach((button) => {
        button.addEventListener('click', () => {
            setViewMode(button.dataset.view);
            localStorage.setItem(storageKey, viewMode);
        });
    });

    setViewMode(viewMode);

    return setViewMode;
}

export function initBoardMemberDashboardAgenda() {
    const root = document.getElementById('bm-dashboard-agenda-search');
    if (!root) {
        return;
    }

    const form = document.getElementById('bm-dashboard-agenda-search-form');
    const results = document.getElementById('bm-dashboard-agenda-search-results');
    const listBody = document.getElementById('bm-dashboard-agenda-list-body');
    const grid = document.getElementById('bm-dashboard-agenda-grid');
    const listWrap = document.getElementById('bm-dashboard-agenda-list-wrap');
    const meta = document.getElementById('bm-dashboard-agenda-search-meta');
    const pagination = document.getElementById('bm-dashboard-agenda-search-pagination');
    const viewToggle = document.getElementById('bm-dashboard-agenda-view-toggle');
    const searchUrl = root.dataset.searchUrl;

    let currentPage = 1;
    let debounceTimer;

    initViewToggle(viewToggle, listWrap, grid, 'splis-bm-agenda-view', null);
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

    function buildParams() {
        const data = new FormData(form);
        const params = new URLSearchParams();

        for (const [key, value] of data.entries()) {
            if (String(value).trim() !== '') {
                params.set(key, value);
            }
        }

        params.set('page', String(currentPage));
        if (!params.has('per_page')) {
            params.set('per_page', '10');
        }
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
            grid.innerHTML = '';
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
            listBody.innerHTML = '<tr><td colspan="6" class="py-12 text-center text-slate-400">No agenda items match your filters.</td></tr>';
            grid.innerHTML = '<p class="col-span-full py-12 text-center text-slate-400">No agenda items match your filters.</p>';
            pagination.innerHTML = '';
            return;
        }

        listBody.innerHTML = items.map(renderCompactListItem).join('');
        grid.innerHTML = items.map(renderCompactGridItem).join('');
        bindTitleTooltips(results);
        renderAjaxPagination(pagination, {
            page,
            lastPage,
            onGoToPage: (target) => {
                currentPage = target;
                fetchResults();
            },
        });
    }
}

export function initBoardMemberDashboardOb() {
    const root = document.getElementById('bm-dashboard-ob-search');
    if (!root) {
        return;
    }

    const form = document.getElementById('bm-dashboard-ob-search-form');
    const results = document.getElementById('bm-dashboard-ob-search-results');
    const list = document.getElementById('bm-dashboard-ob-list');
    const grid = document.getElementById('bm-dashboard-ob-grid');
    const listWrap = document.getElementById('bm-dashboard-ob-list-wrap');
    const meta = document.getElementById('bm-dashboard-ob-search-meta');
    const pagination = document.getElementById('bm-dashboard-ob-search-pagination');
    const viewToggle = document.getElementById('bm-dashboard-ob-view-toggle');
    const searchUrl = root.dataset.searchUrl;

    let currentPage = 1;
    let debounceTimer;

    initViewToggle(viewToggle, listWrap, grid, 'splis-bm-ob-view', null);
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

    function buildParams() {
        const data = new FormData(form);
        const params = new URLSearchParams();

        for (const [key, value] of data.entries()) {
            if (String(value).trim() !== '') {
                params.set(key, value);
            }
        }

        params.set('page', String(currentPage));
        params.set('per_page', '10');
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
            meta.textContent = 'Unable to load sessions.';
            list.innerHTML = '';
            grid.innerHTML = '';
            pagination.innerHTML = '';
        } finally {
            results.classList.remove('opacity-60');
        }
    }

    function renderListItem(session) {
        const action = session.can_view && session.print_url
            ? `<a href="${escapeHtml(session.print_url)}" target="_blank" class="splis-btn-primary !py-1.5 text-sm">View OB</a>`
            : '';

        return `
            <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div>
                    <p class="font-medium text-slate-900 dark:text-slate-100">${escapeHtml(session.title)}</p>
                    <p class="text-sm text-slate-500">${escapeHtml(session.session_date_label || '')}${session.venue ? ` · ${escapeHtml(session.venue)}` : ''}</p>
                </div>
                <div class="flex gap-2">${action}</div>
            </li>
        `;
    }

    function renderGridItem(session) {
        const action = session.can_view && session.print_url
            ? `<a href="${escapeHtml(session.print_url)}" target="_blank" class="splis-doc-list-link text-xs font-semibold">View OB</a>`
            : '';

        return `
            <article class="splis-doc-card flex flex-col gap-3">
                <p class="splis-doc-card-number">${escapeHtml(session.title)}</p>
                <dl class="splis-doc-card-meta">
                    <div><dt>Date</dt><dd>${escapeHtml(session.session_date_label || '—')}</dd></div>
                    <div class="col-span-2"><dt>Venue</dt><dd>${escapeHtml(session.venue || '—')}</dd></div>
                    <div><dt>Type</dt><dd>${escapeHtml(session.kind_label || '—')}</dd></div>
                </dl>
                <div class="mt-auto border-t border-slate-100 pt-3 dark:border-slate-700">${action}</div>
            </article>
        `;
    }

    function renderResults(payload) {
        const sessions = payload.data || [];
        const { total, current_page: page, last_page: lastPage } = payload.meta || {};

        meta.textContent = `${Number(total || 0).toLocaleString()} session(s) found`;

        if (sessions.length === 0) {
            list.innerHTML = '<li class="px-4 py-6 text-center text-sm text-slate-500">No Order of Business documents match your search.</li>';
            grid.innerHTML = '<p class="col-span-full py-12 text-center text-slate-400">No sessions match your search.</p>';
            pagination.innerHTML = '';
            return;
        }

        list.innerHTML = sessions.map(renderListItem).join('');
        grid.innerHTML = sessions.map(renderGridItem).join('');
        renderAjaxPagination(pagination, {
            page,
            lastPage,
            onGoToPage: (target) => {
                currentPage = target;
                fetchResults();
            },
        });
    }
}

export function initBoardMemberAgendaSearch() {
    const root = document.getElementById('bm-agenda-search');
    if (!root) {
        return;
    }

    const form = document.getElementById('bm-agenda-search-form');
    const results = document.getElementById('bm-agenda-search-results');
    const listBody = document.getElementById('bm-agenda-list-body');
    const meta = document.getElementById('bm-agenda-search-meta');
    const pagination = document.getElementById('bm-agenda-search-pagination');
    const searchUrl = root.dataset.searchUrl;
    const committeeId = root.dataset.committeeId || '';
    const statusSelect = document.getElementById('bm-agenda-filter-status');
    const dueSoonInput = document.getElementById('bm-agenda-filter-due-soon');
    const expiringSoonInput = document.getElementById('bm-agenda-filter-expiring-soon');

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
            if (expiringSoonInput) {
                expiringSoonInput.value = '';
            }
            fetchResults();
        }, 0);
    });

    root.querySelectorAll('[data-bm-agenda-stat-filter]').forEach((button) => {
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

    function buildParams() {
        const data = new FormData(form);
        const params = new URLSearchParams();

        for (const [key, value] of data.entries()) {
            if (String(value).trim() !== '') {
                params.set(key, value);
            }
        }

        if (committeeId) {
            params.set('committee_id', committeeId);
        }

        params.set('page', String(currentPage));
        params.set('per_page', '25');
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
        const isCommitteePage = Boolean(committeeId);
        const colSpan = isCommitteePage ? 7 : 9;

        meta.textContent = `${Number(total || 0).toLocaleString()} agenda item(s) found`;
        updateStats(payload.stats);

        if (items.length === 0) {
            listBody.innerHTML = `<tr><td colspan="${colSpan}" class="py-12 text-center text-slate-400">No agenda items match your filters.</td></tr>`;
            pagination.innerHTML = '';
            return;
        }

        listBody.innerHTML = items.map((item) => {
            if (isCommitteePage) {
                return `
                    <tr class="splis-agenda-row" data-href="${escapeHtml(item.url)}">
                        <td class="whitespace-nowrap font-semibold">
                            <a href="${escapeHtml(item.url)}" class="splis-doc-list-link">${escapeHtml(item.tracking_no || item.id)}</a>
                        </td>
                        ${renderTitleCell(item.title)}
                        <td class="hidden md:table-cell">${escapeHtml(item.sender || '—')}</td>
                        <td class="hidden sm:table-cell whitespace-nowrap">${formatDate(item.date_received)}</td>
                        <td class="whitespace-nowrap">${formatDate(item.due_date)}</td>
                        ${renderDaysLeftCell(item.days_left_label, item.days_left_tone)}
                        <td>${renderStatusBadge(item.status, item.status_label)}</td>
                    </tr>
                `;
            }

            return renderFullListItem(item);
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
