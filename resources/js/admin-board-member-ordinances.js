import { renderAjaxPagination } from './pagination';

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

function renderStatusBadge(status, label) {
    const badgeClass = status === 'passed' ? 'splis-badge-linked' : 'splis-badge';

    return `<span class="${badgeClass}">${escapeHtml(label)}</span>`;
}

function renderRow(record) {
    return `
        <tr>
            <td class="whitespace-nowrap">
                <a href="${escapeHtml(record.url)}" class="splis-link font-semibold">${escapeHtml(record.number_label)}</a>
                <p class="mt-0.5 text-xs font-normal text-slate-500 dark:text-slate-400">${escapeHtml(record.series_label || '')}</p>
            </td>
            <td>${escapeHtml(record.subject || '—')}</td>
            <td class="whitespace-nowrap">${formatDate(record.date_enacted)}</td>
            <td class="whitespace-nowrap">${formatDate(record.date_approved)}</td>
            <td>${renderStatusBadge(record.status, record.status_label)}</td>
        </tr>
    `;
}

export function initAdminBoardMemberOrdinancesSearch() {
    const root = document.getElementById('bm-authored-ordinances');
    if (!root) {
        return;
    }

    const form = document.getElementById('bm-authored-ordinances-form');
    const hint = document.getElementById('bm-authored-ordinances-hint');
    const results = document.getElementById('bm-authored-ordinances-results');
    const memberName = document.getElementById('bm-authored-ordinances-member-name');
    const meta = document.getElementById('bm-authored-ordinances-meta');
    const tableRoot = document.getElementById('bm-authored-ordinances-table');
    const body = tableRoot?.querySelector('tbody');
    const pagination = document.getElementById('bm-authored-ordinances-pagination');
    const searchUrl = root.dataset.searchUrl;

    if (!form || !hint || !results || !memberName || !meta || !body || !pagination || !searchUrl) {
        return;
    }

    let currentPage = 1;
    let debounceTimer;

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        currentPage = 1;
        fetchResults();
    });

    form.addEventListener('change', () => {
        currentPage = 1;
        fetchResults();
    });

    form.addEventListener('input', (event) => {
        if (event.target?.name !== 'q') {
            return;
        }

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            currentPage = 1;
            fetchResults();
        }, 350);
    });

    const initialMemberId = root.dataset.initialMemberId;
    if (initialMemberId) {
        fetchResults();
    }

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

    function updateUrl() {
        const params = buildParams();
        params.delete('page');

        const query = params.toString();
        const nextUrl = query ? `${window.location.pathname}?${query}` : window.location.pathname;
        window.history.replaceState(null, '', nextUrl);
    }

    async function fetchResults() {
        const memberId = form.elements.board_member_id?.value;

        if (!memberId) {
            hint.hidden = false;
            hint.textContent = 'Select a Board Member to view authored ordinances.';
            results.hidden = true;
            pagination.innerHTML = '';
            updateUrl();
            return;
        }

        hint.hidden = false;
        hint.textContent = 'Loading ordinances…';
        results.hidden = true;
        tableRoot.classList.add('opacity-60');

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
            updateUrl();
        } catch {
            hint.textContent = 'Unable to load ordinances.';
            results.hidden = true;
            pagination.innerHTML = '';
        } finally {
            tableRoot.classList.remove('opacity-60');
        }
    }

    function renderResults(payload) {
        const items = payload.data || [];
        const member = payload.member;
        const { total, current_page: page, last_page: lastPage } = payload.meta || {};

        if (!member) {
            hint.hidden = false;
            hint.textContent = 'Select a Board Member to view authored ordinances.';
            results.hidden = true;
            pagination.innerHTML = '';
            return;
        }

        hint.hidden = true;
        results.hidden = false;
        memberName.textContent = member.name;
        meta.textContent = `${Number(total || 0).toLocaleString()} Ordinance(s)`;

        if (items.length === 0) {
            body.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-sm text-slate-500">No Authored Ordinances found for this Board Member.</td></tr>';
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
