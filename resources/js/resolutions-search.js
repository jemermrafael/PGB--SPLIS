import { renderAjaxPagination } from './pagination';
import { applyKeywordFromQuery } from './search-query';
import { bindTitleTooltips, renderTruncatedTitle, truncateWords } from './title-tooltip';
import { preferredDocView } from './doc-view';
import { pdfModalTriggerAttrs } from './pdf-embed-url';
import { initConfirmDialog, showConfirmDialog } from './confirm-dialog';
import {
    escapeHtml,
    renderAuthorMeta,
    renderCommitteeMeta,
    renderDateMeta,
    renderStatusBadge,
} from './list-meta';

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

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function renderTitleCell(title) {
    const { display, full, truncated } = truncateWords(title);

    return `<td class="splis-table-title splis-table-title--list">${renderTruncatedTitle(display, full, truncated)}</td>`;
}

function renderPdfCell(doc) {
    const status = doc.pdf_status || (doc.has_pdf ? 'local' : 'none');
    const titles = {
        local: 'View PDF (file on server)',
        external: 'View PDF (external link)',
        missing: 'PDF path set but file missing on server',
        none: 'No PDF linked',
    };

    if (status === 'local' || status === 'external') {
        return `<td class="text-center">
            <a ${pdfModalTriggerAttrs(doc.pdf_url, `${doc.number || 'Resolution'} PDF`)} class="splis-doc-pdf-icon" title="${titles[status]}" aria-label="${titles[status]}">${pdfListIcon}</a>
        </td>`;
    }

    if (status === 'missing') {
        return `<td class="text-center" title="${titles.missing}">
            <span class="inline-flex text-amber-600 dark:text-amber-400" aria-label="${titles.missing}">⚠</span>
        </td>`;
    }

    return `<td class="text-center"><span class="text-slate-300" title="${titles.none}">—</span></td>`;
}

function renderSelectCell(doc, canBulkDelete) {
    if (!canBulkDelete) {
        return '';
    }

    return `<td>
        <input
            type="checkbox"
            value="${escapeHtml(String(doc.id))}"
            data-resolution-checkbox
            class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
            aria-label="Select resolution ${escapeHtml(doc.number || String(doc.id))}"
        >
    </td>`;
}

function renderListItem(doc, canBulkDelete) {
    return `
        <tr>
            ${renderSelectCell(doc, canBulkDelete)}
            ${renderPdfCell(doc)}
            <td class="whitespace-nowrap font-semibold">
                <a href="${escapeHtml(doc.url)}" class="splis-doc-list-link">${escapeHtml(doc.number)}</a>
            </td>
            ${renderTitleCell(doc.title)}
            <td class="hidden md:table-cell">${renderAuthorMeta(doc.author)}</td>
            <td class="hidden lg:table-cell">${renderCommitteeMeta(doc.committee, { key: doc.committee_icon_key, url: doc.committee_icon_url })}</td>
            <td class="hidden sm:table-cell whitespace-nowrap">${renderDateMeta(formatDate(doc.date))}</td>
            <td>${renderStatusBadge(doc.status)}</td>
            <td class="hidden lg:table-cell text-slate-500">${escapeHtml(doc.series || '—')}</td>
        </tr>
    `;
}

function renderGridItem(doc, canBulkDelete) {
    const { display, full, truncated } = truncateWords(doc.title);
    const select = canBulkDelete
        ? `<label class="inline-flex items-center gap-2 text-xs text-slate-500">
                <input
                    type="checkbox"
                    value="${escapeHtml(String(doc.id))}"
                    data-resolution-checkbox
                    class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                    aria-label="Select resolution ${escapeHtml(doc.number || String(doc.id))}"
                >
                Select
            </label>`
        : '';

    return `
        <article class="splis-doc-card flex flex-col gap-3">
            <div class="flex items-start justify-between gap-2">
                <a href="${escapeHtml(doc.url)}" class="splis-doc-card-number">${escapeHtml(doc.number)}</a>
                <span class="text-xs font-medium text-slate-500">${escapeHtml(doc.series || '—')}</span>
            </div>
            <p class="splis-doc-card-title">${renderTruncatedTitle(display, full, truncated)}</p>
            <dl class="splis-doc-card-meta">
                <div><dt>Author</dt><dd>${renderAuthorMeta(doc.author)}</dd></div>
                <div><dt>Date</dt><dd>${renderDateMeta(formatDate(doc.date))}</dd></div>
                <div class="col-span-2"><dt>Committee</dt><dd>${renderCommitteeMeta(doc.committee, { key: doc.committee_icon_key, url: doc.committee_icon_url })}</dd></div>
            </dl>
            <div class="mt-auto flex items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-700">
                ${renderStatusBadge(doc.status)}
                ${doc.has_pdf
                    ? `<a ${pdfModalTriggerAttrs(doc.pdf_url, `${doc.number || 'Resolution'} PDF`)} class="splis-doc-list-link text-xs font-semibold">View PDF</a>`
                    : '<span class="text-xs text-slate-400">No PDF</span>'}
            </div>
            ${select ? `<div class="pt-1">${select}</div>` : ''}
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
    'from_agenda',
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
            return Array.from(field).some((input) => (input.type === 'checkbox' ? input.checked : String(input.value).trim() !== ''));
        }

        if (field.type === 'checkbox') {
            return field.checked;
        }

        return String(field.value).trim() !== '';
    });
}

function syncAdvancedFiltersPanel(form) {
    const advanced = document.getElementById('resolutions-advanced-filters');
    if (!advanced) {
        return;
    }

    if (advancedFiltersActive(form)) {
        advanced.open = true;
    }
}

function parsePerPageOptions(raw) {
    try {
        const parsed = JSON.parse(raw || '[]');
        if (!Array.isArray(parsed)) {
            return [15, 25, 50, 100];
        }

        return parsed.map(Number).filter((value) => Number.isFinite(value) && value > 0);
    } catch {
        return [15, 25, 50, 100];
    }
}

export function initResolutionsSearch() {
    const root = document.getElementById('resolutions-search');
    if (!root) {
        return;
    }

    const form = document.getElementById('resolutions-search-form');
    const results = document.getElementById('resolutions-search-results');
    const listBody = document.getElementById('resolutions-list-body');
    const grid = document.getElementById('resolutions-grid');
    const listWrap = document.getElementById('resolutions-list-wrap');
    const meta = document.getElementById('resolutions-search-meta');
    const pagination = document.getElementById('resolutions-search-pagination');
    const viewToggle = document.getElementById('resolutions-view-toggle');
    const selectAll = root.querySelector('[data-resolution-select-all]');
    const bulkDeleteBtn = root.querySelector('[data-resolution-bulk-delete]');
    const selectedCount = root.querySelector('[data-resolution-selected-count]');
    const bulkStatus = document.getElementById('resolutions-bulk-status');
    const searchUrl = root.dataset.searchUrl;
    const bulkDestroyUrl = root.dataset.bulkDestroyUrl || '';
    const canBulkDelete = root.dataset.canBulkDelete === '1';
    const perPageOptions = parsePerPageOptions(root.dataset.perPageOptions);
    const storedPerPage = Number(localStorage.getItem('splis-resolutions-per-page'));
    const initialPerPage = Number(root.dataset.perPage) || 15;

    let currentPage = 1;
    let perPage = perPageOptions.includes(storedPerPage) ? storedPerPage : (perPageOptions.includes(initialPerPage) ? initialPerPage : 15);
    let viewMode = preferredDocView('splis-doc-view');
    let debounceTimer;
    let columnCount = canBulkDelete ? 9 : 8;

    initConfirmDialog();
    setViewMode(viewMode);
    applyKeywordFromQuery(form);
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
            const advanced = document.getElementById('resolutions-advanced-filters');
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

    selectAll?.addEventListener('change', () => {
        const checked = selectAll.checked;
        checkboxes().forEach((box) => {
            box.checked = checked;
        });
        // Keep list/grid counterparts in sync so IDs are not duplicated on submit.
        mirrorCheckboxState();
        syncSelectionUi();
    });

    results.addEventListener('change', (event) => {
        if (event.target?.matches?.('[data-resolution-checkbox]')) {
            const value = event.target.value;
            const checked = event.target.checked;
            root.querySelectorAll(`[data-resolution-checkbox][value="${CSS.escape(value)}"]`).forEach((box) => {
                box.checked = checked;
            });
            syncSelectionUi();
        }
    });

    bulkDeleteBtn?.addEventListener('click', async () => {
        const ids = selectedIds();
        if (ids.length === 0 || !bulkDestroyUrl) {
            return;
        }

        const confirmed = await showConfirmDialog({
            title: ids.length === 1 ? 'Delete resolution?' : `Delete ${ids.length} resolutions?`,
            message: 'Selected resolutions will be moved to trash. Superadmins can restore them later.',
            confirmLabel: ids.length === 1 ? 'Delete' : `Delete ${ids.length}`,
            danger: true,
        });

        if (!confirmed) {
            return;
        }

        bulkDeleteBtn.disabled = true;

        try {
            const response = await fetch(bulkDestroyUrl, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ids }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const validation = payload?.errors
                    ? Object.values(payload.errors).flat().join(' ')
                    : (payload?.message || 'Bulk delete failed');
                throw new Error(validation);
            }

            if (bulkStatus) {
                bulkStatus.textContent = payload.message || 'Selected resolutions moved to trash.';
                bulkStatus.classList.remove('hidden');
            }

            await fetchResults();
        } catch (error) {
            if (bulkStatus) {
                bulkStatus.textContent = error instanceof Error && error.message
                    ? error.message
                    : 'Unable to delete selected resolutions.';
                bulkStatus.classList.remove('hidden');
            }
            syncSelectionUi();
        }
    });

    function activeResultsContainer() {
        return viewMode === 'grid' ? grid : listWrap;
    }

    function checkboxes() {
        return Array.from(activeResultsContainer()?.querySelectorAll('[data-resolution-checkbox]') ?? []);
    }

    function selectedIds() {
        return [...new Set(
            checkboxes()
                .filter((box) => box.checked)
                .map((box) => Number(box.value))
                .filter((id) => Number.isFinite(id) && id > 0),
        )];
    }

    function mirrorCheckboxState() {
        checkboxes().forEach((box) => {
            root.querySelectorAll(`[data-resolution-checkbox][value="${CSS.escape(box.value)}"]`).forEach((peer) => {
                peer.checked = box.checked;
            });
        });
    }

    function syncSelectionUi() {
        const boxes = checkboxes();
        const selected = selectedIds();
        const total = boxes.length;

        if (selectAll) {
            selectAll.checked = total > 0 && selected.length === total;
            selectAll.indeterminate = selected.length > 0 && selected.length < total;
        }

        if (selectedCount) {
            selectedCount.textContent = selected.length > 0
                ? `${selected.length} selected`
                : 'None selected';
        }

        if (bulkDeleteBtn) {
            bulkDeleteBtn.disabled = selected.length === 0;
        }
    }

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
        params.set('per_page', String(perPage));
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
            meta.textContent = 'Unable to load resolutions.';
            listBody.innerHTML = '';
            grid.innerHTML = '';
            pagination.innerHTML = '';
            syncSelectionUi();
        } finally {
            results.classList.remove('opacity-60');
        }
    }

    function renderResults(payload) {
        const docs = payload.data || [];
        const {
            total,
            current_page: page,
            last_page: lastPage,
            per_page: responsePerPage,
            per_page_options: responseOptions,
        } = payload.meta || {};

        if (Number.isFinite(Number(responsePerPage))) {
            perPage = Number(responsePerPage);
        }

        const options = Array.isArray(responseOptions) && responseOptions.length > 0
            ? responseOptions.map(Number).filter((value) => Number.isFinite(value) && value > 0)
            : perPageOptions;

        meta.textContent = `${Number(total || 0).toLocaleString()} Resolution(s) found`;

        if (docs.length === 0) {
            listBody.innerHTML = `<tr><td colspan="${columnCount}" class="py-12 text-center text-slate-400">No Resolutions match your filters.</td></tr>`;
            grid.innerHTML = '<p class="col-span-full py-12 text-center text-slate-400">No Resolutions match your filters.</p>';
            renderPagination(page || 1, lastPage || 1, options);
            syncSelectionUi();
            return;
        }

        listBody.innerHTML = docs.map((doc) => renderListItem(doc, canBulkDelete)).join('');
        grid.innerHTML = docs.map((doc) => renderGridItem(doc, canBulkDelete)).join('');
        bindTitleTooltips(results);
        renderPagination(page, lastPage, options);
        syncSelectionUi();
    }

    function renderPagination(page, lastPage, options = perPageOptions) {
        renderAjaxPagination(pagination, {
            page,
            lastPage,
            perPage,
            perPageOptions: options,
            onGoToPage: (target) => {
                currentPage = target;
                fetchResults();
                root.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },
            onPerPageChange: (value) => {
                perPage = value;
                localStorage.setItem('splis-resolutions-per-page', String(value));
                currentPage = 1;
                fetchResults();
            },
        });
    }
}
