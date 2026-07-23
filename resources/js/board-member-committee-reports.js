import { bindTitleTooltips, renderTruncatedTitle, truncateWords } from './title-tooltip';

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function renderAgendaItem(item, checkedIds) {
    const { display, full, truncated } = truncateWords(item.title, 20);
    const checked = checkedIds.has(Number(item.id)) ? ' checked' : '';

    return `
        <label class="flex cursor-pointer items-start gap-2 rounded-lg px-2 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800/60">
            <input
                type="checkbox"
                name="agenda_item_ids[]"
                value="${escapeHtml(item.id)}"
                class="mt-1 rounded border-slate-300 text-brand-600 focus:ring-brand-500"${checked}
            >
            <span class="min-w-0 flex-1">
                <span class="font-semibold text-slate-900 dark:text-slate-100">${escapeHtml(item.number)}</span>
                <span class="mt-0.5 block text-slate-600 dark:text-slate-300">
                    ${renderTruncatedTitle(display, full, truncated)}
                </span>
                ${item.committee
                    ? `<span class="mt-0.5 block text-xs text-slate-500">${escapeHtml(item.committee)}</span>`
                    : ''}
            </span>
        </label>
    `;
}

export function initBoardMemberCommitteeReportAgendaSearch() {
    const root = document.getElementById('bm-cr-agenda-panel');
    if (!root) {
        return;
    }

    const list = document.getElementById('bm-cr-agenda-list');
    const committeeSelect = document.getElementById('bm-cr-committee-id');
    const searchInput = document.getElementById('bm-cr-q');
    const searchUrl = root.dataset.searchUrl;

    if (!list || !committeeSelect || !searchInput || !searchUrl) {
        return;
    }

    let debounceTimer;
    let requestId = 0;

    committeeSelect.addEventListener('change', () => {
        fetchAgendas();
        updateUrl();
    });

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            fetchAgendas();
            updateUrl();
        }, 300);
    });

    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            clearTimeout(debounceTimer);
            fetchAgendas();
            updateUrl();
        }
    });

    function selectedIds() {
        return new Set(
            [...list.querySelectorAll('input[name="agenda_item_ids[]"]:checked')].map((input) => Number(input.value)),
        );
    }

    function updateUrl() {
        const params = new URLSearchParams();
        const q = searchInput.value.trim();
        const committeeId = committeeSelect.value;

        if (q !== '') {
            params.set('q', q);
        }
        if (committeeId !== '') {
            params.set('committee_id', committeeId);
        }

        const query = params.toString();
        const nextUrl = query ? `${window.location.pathname}?${query}` : window.location.pathname;
        window.history.replaceState(null, '', nextUrl);
    }

    async function fetchAgendas() {
        const checked = selectedIds();
        const params = new URLSearchParams();
        const q = searchInput.value.trim();
        const committeeId = committeeSelect.value;

        if (q !== '') {
            params.set('q', q);
        }
        if (committeeId !== '') {
            params.set('committee_id', committeeId);
        }

        const currentRequest = ++requestId;
        list.classList.add('opacity-60');

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
            if (currentRequest !== requestId) {
                return;
            }

            const items = payload.data || [];
            if (items.length === 0) {
                const filtered = q !== '' || committeeId !== '';
                list.innerHTML = `<p class="px-2 py-8 text-center text-sm text-slate-500">${
                    filtered
                        ? 'No chairmanship agenda items matched your filter.'
                        : 'No open chairmanship agenda items need a committee report.'
                }</p>`;
            } else {
                list.innerHTML = items.map((item) => renderAgendaItem(item, checked)).join('');
                bindTitleTooltips(list);
            }
        } catch {
            if (currentRequest !== requestId) {
                return;
            }
            list.innerHTML = '<p class="px-2 py-8 text-center text-sm text-red-600">Unable to load agenda items.</p>';
        } finally {
            if (currentRequest === requestId) {
                list.classList.remove('opacity-60');
            }
        }
    }
}
