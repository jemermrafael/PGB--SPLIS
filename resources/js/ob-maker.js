import { bindTitleTooltips, renderTruncatedTitle, truncateWords } from './title-tooltip';

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function debounce(fn, ms) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), ms);
    };
}

function normalizeRomanNumeral(value) {
    return String(value ?? '')
        .trim()
        .toUpperCase()
        .replace(/\.$/, '');
}

/** Roman numeral field display: always use a trailing period. */
function displayRomanNumeral(value) {
    const normalized = normalizeRomanNumeral(value);

    if (normalized === '') {
        return '';
    }

    return `${normalized}.`;
}

function storeRomanNumeral(value) {
    return displayRomanNumeral(value);
}

export function initObMaker() {
    const root = document.getElementById('ob-maker');
    if (!root) {
        return;
    }

    const config = JSON.parse(root.dataset.config);
    const canEdit = root.dataset.canEdit === '1';
    const urls = config.urls;
    const committees = config.committees ?? [];

    function normalizeRomanBlock(block) {
        if (block.type !== 'roman_section' || !block.content) {
            return block;
        }

        return {
            ...block,
            content: {
                ...block.content,
                numeral: storeRomanNumeral(block.content.numeral ?? ''),
            },
        };
    }

    function normalizeBlocks(list) {
        return list.map(normalizeRomanBlock);
    }

    let blocks = normalizeBlocks([...config.initial.blocks]).sort((a, b) => a.sort_order - b.sort_order);
    let documentState = { ...config.initial.document };
    let selectedBlockId = blocks[0]?.id ?? null;
    let selectedAgendaIds = new Set();
    let poolItems = [];
    let poolPage = 1;
    let poolMeta = { last_page: 1, current_page: 1, total: 0 };
    let poolLoading = false;
    let poolObserver = null;

    const blocksList = document.getElementById('ob-blocks-list');
    const blocksEmpty = document.getElementById('ob-blocks-empty');
    const saveStatus = document.getElementById('ob-save-status');
    const titleInput = document.getElementById('ob-doc-title');
    const statusSelect = document.getElementById('ob-doc-status');
    const agendaPoolEl = document.getElementById('ob-agenda-pool');
    const agendaSearchInput = document.getElementById('ob-agenda-search');
    const agendaSectionSelect = document.getElementById('ob-agenda-section');
    const addBlockTypesEl = document.getElementById('ob-add-block-types');

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    function blockUrl(template, blockId) {
        return template.replace('__BLOCK__', String(blockId));
    }

    function setStatus(message, isError = false) {
        if (!saveStatus) {
            return;
        }
        saveStatus.textContent = message;
        saveStatus.classList.toggle('text-red-600', isError);
        saveStatus.classList.toggle('text-slate-500', !isError);
    }

    async function api(url, options = {}) {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers ?? {}),
            },
            credentials: 'same-origin',
            ...options,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().find(Boolean)
                : null;
            throw new Error(validationMessage ?? data.message ?? 'Request failed.');
        }

        return data;
    }

    function renderAddBlockButtons() {
        if (!addBlockTypesEl) {
            return;
        }

        addBlockTypesEl.innerHTML = config.blockTypes
            .map(
                (type) =>
                    `<button type="button" class="splis-ob-chip" data-add-type="${escapeHtml(type.value)}">${escapeHtml(type.label)}</button>`,
            )
            .join('');
    }

    function renderBlockControls(block, index) {
        if (!canEdit) {
            return '';
        }

        return `
            <div class="splis-ob-block-actions">
                <button type="button" class="splis-ob-icon-btn" data-move-up="${block.id}" ${index === 0 ? 'disabled' : ''} title="Move up">↑</button>
                <button type="button" class="splis-ob-icon-btn" data-move-down="${block.id}" ${index === blocks.length - 1 ? 'disabled' : ''} title="Move down">↓</button>
                <button type="button" class="splis-ob-icon-btn splis-ob-icon-btn--danger" data-delete-block="${block.id}" title="Delete">×</button>
            </div>
        `;
    }

    function resolveCommitteeId(c) {
        if (c.committee_id) {
            return String(c.committee_id);
        }

        const name = String(c.committee_name ?? '').trim();
        if (name === '') {
            return '';
        }

        const match = committees.find((item) => {
            const committeeName = item.name.toLowerCase();
            const stored = name.toLowerCase();
            const normalizedStored = stored
                .replace(/^sp committee on /i, '')
                .replace(/^committee on /i, '')
                .trim();

            return committeeName === stored
                || committeeName === normalizedStored
                || stored.includes(committeeName);
        });
        return match ? String(match.id) : '';
    }

    function renderCommitteeSelect(c, disabled, fieldLabel = 'Committee', hint = '') {
        const selectedId = resolveCommitteeId(c);
        const options = committees
            .map(
                (item) =>
                    `<option value="${item.id}" data-committee-name="${escapeHtml(item.name)}" ${String(item.id) === selectedId ? 'selected' : ''}>${escapeHtml(item.name)}</option>`,
            )
            .join('');

        const hintHtml = hint
            ? `<p class="mt-1 text-xs text-slate-500">${escapeHtml(hint)}</p>`
            : '';

        return `
            <div class="md:col-span-2">
                <label class="splis-label">${escapeHtml(fieldLabel)}</label>
                <select class="splis-select splis-ob-block-field" data-field="committee_id" ${disabled}>
                    <option value="">Select committee…</option>
                    ${options}
                </select>
                ${hintHtml}
            </div>
        `;
    }

    function renderAgendaMetaFields(c, disabled, options = {}) {
        const {
            includeKind = false,
            includeCommittee = false,
            referralNotePlaceholder = '(Referred last November 24, 2025)',
        } = options;
        const agendaNo = c.agenda_no ?? c.session_agenda_no ?? '';

        const kindField = includeKind
            ? `
                <div>
                    <label class="splis-label">Kind</label>
                    <select class="splis-select splis-ob-block-field" data-field="kind" ${disabled}>
                        <option value="urgent" ${c.kind === 'urgent' ? 'selected' : ''}>Urgent request</option>
                        <option value="regular" ${(c.kind ?? 'regular') === 'regular' ? 'selected' : ''}>Regular unassigned</option>
                    </select>
                </div>
            `
            : '';

        const committeeField = includeCommittee
            ? renderCommitteeSelect(
                  c,
                  disabled,
                  'Committee',
                  'Group this item under a committee in A. Unfinished business.',
              )
            : '';

        return `
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <label class="splis-label">Agenda no.</label>
                    <input type="text" class="splis-input splis-ob-block-field" data-field="agenda_no" value="${escapeHtml(agendaNo)}" ${disabled}>
                </div>
                ${kindField}
                ${committeeField}
                <div>
                    <label class="splis-label">Date of receipt</label>
                    <input type="text" class="splis-input splis-ob-block-field" data-field="date_received" value="${escapeHtml(c.date_received ?? '')}" ${disabled}>
                </div>
                <div>
                    <label class="splis-label">Prescription</label>
                    <input type="text" class="splis-input splis-ob-block-field" data-field="prescription" value="${escapeHtml(c.prescription ?? '')}" ${disabled}>
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label">Title</label>
                    <textarea class="splis-textarea splis-ob-block-field" data-field="title" rows="4" ${disabled}>${escapeHtml(c.title ?? '')}</textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label">Referral note</label>
                    <textarea class="splis-textarea splis-ob-block-field" data-field="referral_note" rows="3" ${disabled} placeholder="${escapeHtml(referralNotePlaceholder)}">${escapeHtml(c.referral_note ?? '')}</textarea>
                </div>
            </div>
        `;
    }

    function romanSectionFieldFlags(c) {
        const title = String(c.title ?? '').trim().toUpperCase();
        const numeral = normalizeRomanNumeral(c.numeral);

        const isNumeral = (value) => numeral === value || numeral.startsWith(`${value}.`);

        let showTitle = true;
        let showBody = true;
        let showSubLabel = true;

        if (isNumeral('I') && title.includes('ROLL CALL')) {
            showBody = false;
            showSubLabel = false;
        } else if (isNumeral('II') && title.includes('APPEARANCE')) {
            showBody = false;
            showSubLabel = false;
        } else if (isNumeral('III')) {
            showTitle = false;
            showSubLabel = false;
        } else if (isNumeral('IV') && title.includes('COMMITTEE')) {
            showBody = false;
            showSubLabel = false;
        } else if (isNumeral('V') && title.includes('PRIVILEGE')) {
            showBody = false;
            showSubLabel = false;
        } else if (isNumeral('VI') && title.includes('CALENDAR')) {
            showBody = false;
            showSubLabel = false;
        } else if (isNumeral('VII') || title.includes('ANNOUNCEMENTS')) {
            showBody = false;
            showSubLabel = false;
        }

        return { showTitle, showBody, showSubLabel };
    }

    function isAnnouncementsSection(c) {
        const title = String(c.title ?? '').trim().toUpperCase();
        const numeral = normalizeRomanNumeral(c.numeral);

        return numeral === 'VII' || title.includes('ANNOUNCEMENTS');
    }

    function renderBlockEditor(block) {
        const c = block.content ?? {};
        const disabled = canEdit ? '' : 'disabled';

        switch (block.type) {
            case 'heading':
            case 'committee_group':
            case 'subsection_label':
                return `<textarea class="splis-textarea splis-ob-block-field" data-field="text" rows="2" ${disabled}>${escapeHtml(c.text ?? '')}</textarea>`;
            case 'roman_section': {
                const flags = romanSectionFieldFlags(c);
                const titleField = flags.showTitle
                    ? `
                        <div>
                            <label class="splis-label">Section title</label>
                            <input type="text" class="splis-input splis-ob-block-field" data-field="title" value="${escapeHtml(c.title ?? '')}" ${disabled}>
                        </div>
                    `
                    : '';
                const bodyField = flags.showBody
                    ? `
                        <div class="md:col-span-2">
                            <label class="splis-label">Section content</label>
                            <textarea class="splis-textarea splis-ob-block-field" data-field="body" rows="3" ${disabled} placeholder="Section body text">${escapeHtml(c.body ?? '')}</textarea>
                        </div>
                    `
                    : '';
                const subLabelField = flags.showSubLabel
                    ? `
                        <div class="md:col-span-2">
                            <label class="splis-label">Sub-label (calendar row)</label>
                            <input type="text" class="splis-input splis-ob-block-field" data-field="sub_label" value="${escapeHtml(c.sub_label ?? '')}" ${disabled}>
                        </div>
                    `
                    : '';

                const announcementActions =
                    isAnnouncementsSection(c) && canEdit
                        ? `
                        <div class="md:col-span-2 border-t border-slate-200 pt-3 dark:border-slate-700">
                            <p class="mb-2 text-xs text-slate-500">Add two-column announcement rows below this section.</p>
                            <button type="button" class="splis-btn-secondary splis-ob-add-announcement" data-after-block="${block.id}">Add announcement row</button>
                        </div>
                    `
                        : '';


                return `
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label class="splis-label">Roman numeral</label>
                            <input type="text" class="splis-input splis-ob-block-field" data-field="numeral" value="${escapeHtml(c.numeral ?? '')}" ${disabled}>
                        </div>
                        ${titleField}
                        ${bodyField}
                        ${subLabelField}
                        ${announcementActions}
                    </div>
                `;
            }
            case 'paragraph':
                return `<textarea class="splis-textarea splis-ob-block-field" data-field="text" rows="4" ${disabled}>${escapeHtml(c.text ?? '')}</textarea>`;
            case 'committee_report':
                return `
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label class="splis-label">Row no. (IV section only)</label>
                            <input type="number" class="splis-input splis-ob-block-field" data-field="row_no" value="${escapeHtml(c.row_no ?? '')}" ${disabled}>
                        </div>
                        <div>
                            <label class="splis-label">Agenda no.</label>
                            <input type="text" class="splis-input splis-ob-block-field" data-field="agenda_no" value="${escapeHtml(
                                Array.isArray(c.agenda_nos) && c.agenda_nos.length > 0
                                    ? c.agenda_nos.join(', ')
                                    : (c.agenda_no ?? c.session_agenda_no ?? ''),
                            )}" ${disabled} placeholder="e.g. 262, 272, 273">
                        </div>
                        ${renderCommitteeSelect(
                            c,
                            disabled,
                            'SP Committee',
                            c.needs_committee
                                ? 'Select the committee for this report (from agenda details or choose here).'
                                : '',
                        )}
                        <div class="md:col-span-2">
                            <label class="splis-label">Chaired by</label>
                            <input type="text" class="splis-input splis-ob-block-field" data-field="chair_name" value="${escapeHtml(c.chair_name ?? '')}" ${disabled}>
                        </div>
                    </div>
                `;
            case 'unfinished_committee':
                return `
                    <div class="space-y-3">
                        <div>
                            <label class="splis-label">Committee header</label>
                            <textarea class="splis-textarea splis-ob-block-field" data-field="committee_name" rows="2" ${disabled}>${escapeHtml(c.committee_name ?? '')}</textarea>
                        </div>
                        <div>
                            <label class="splis-label">Chair</label>
                            <input type="text" class="splis-input splis-ob-block-field" data-field="chair_name" value="${escapeHtml(c.chair_name ?? '')}" ${disabled}>
                        </div>
                    </div>
                `;
            case 'unfinished_agenda':
                return renderAgendaMetaFields(c, disabled, { includeCommittee: c.needs_committee === true });
            case 'unassigned_agenda':
                return renderAgendaMetaFields(c, disabled, {
                    includeKind: true,
                    referralNotePlaceholder:
                        (c.kind ?? 'regular') === 'urgent'
                            ? 'Sponsored by: SP Committee on …\nChaired by: Board Member …'
                            : '(To be referred to SP Committee on …, Chaired by: … )',
                });
            case 'reading_agenda':
                return `
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label class="splis-label">Reading</label>
                                <select class="splis-select splis-ob-block-field" data-field="reading" ${disabled}>
                                    <option value="2nd" ${(c.reading ?? '2nd') === '2nd' ? 'selected' : ''}>2nd reading</option>
                                    <option value="3rd" ${c.reading === '3rd' ? 'selected' : ''}>3rd reading</option>
                                </select>
                            </div>
                        </div>
                        ${renderAgendaMetaFields(c, disabled)}
                    </div>
                `;
            case 'announcement':
                return `
                    <div class="space-y-3">
                        <div>
                            <label class="splis-label">Column 1</label>
                            <textarea class="splis-textarea splis-ob-block-field" data-field="column_1" rows="3" ${disabled}>${escapeHtml(c.column_1 ?? c.date_received ?? '')}</textarea>
                        </div>
                        <div>
                            <label class="splis-label">Column 2</label>
                            <textarea class="splis-textarea splis-ob-block-field" data-field="column_2" rows="3" ${disabled}>${escapeHtml(c.column_2 ?? c.title ?? '')}</textarea>
                        </div>
                    </div>
                `;
            case 'adjournment':
                return '<p class="text-sm text-slate-500 italic">VIII — ADJOURNMENT (fixed section label in print view).</p>';
            case 'agenda_line':
                return `
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label class="splis-label">Session agenda no.</label>
                            <input type="number" class="splis-input splis-ob-block-field" data-field="session_agenda_no" value="${escapeHtml(c.session_agenda_no ?? '')}" ${disabled}>
                        </div>
                        <div>
                            <label class="splis-label">Tracking no.</label>
                            <input type="text" class="splis-input splis-ob-block-field" data-field="tracking_no" value="${escapeHtml(c.tracking_no ?? '')}" ${disabled}>
                        </div>
                        <div>
                            <label class="splis-label">Date of receipt</label>
                            <input type="text" class="splis-input splis-ob-block-field" data-field="date_received" value="${escapeHtml(c.date_received ?? '')}" ${disabled}>
                        </div>
                        <div>
                            <label class="splis-label">Prescription</label>
                            <input type="text" class="splis-input splis-ob-block-field" data-field="prescription" value="${escapeHtml(c.prescription ?? '')}" ${disabled}>
                        </div>
                        <div class="md:col-span-2">
                            <label class="splis-label">Title</label>
                            <textarea class="splis-textarea splis-ob-block-field" data-field="title" rows="3" ${disabled}>${escapeHtml(c.title ?? '')}</textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="splis-label">Referral note</label>
                            <input type="text" class="splis-input splis-ob-block-field" data-field="referral_note" value="${escapeHtml(c.referral_note ?? '')}" ${disabled}>
                        </div>
                    </div>
                `;
            case 'table': {
                const headers = c.headers ?? ['Column 1', 'Column 2'];
                const rows = c.rows ?? [];
                const headerInputs = headers
                    .map(
                        (header, i) =>
                            `<input type="text" class="splis-input splis-ob-table-header" data-header-index="${i}" value="${escapeHtml(header)}" ${disabled}>`,
                    )
                    .join('');
                const rowHtml = rows
                    .map(
                        (row, rowIndex) => `
                        <div class="splis-ob-table-row" data-row-index="${rowIndex}">
                            ${(row ?? []).map(
                                (cell, cellIndex) =>
                                    `<input type="text" class="splis-input splis-ob-table-cell" data-row-index="${rowIndex}" data-cell-index="${cellIndex}" value="${escapeHtml(cell)}" ${disabled}>`,
                            ).join('')}
                            ${canEdit ? `<button type="button" class="splis-ob-icon-btn splis-ob-icon-btn--danger" data-remove-row="${rowIndex}">×</button>` : ''}
                        </div>`,
                    )
                    .join('');

                return `
                    <div class="space-y-3">
                        <div>
                            <label class="splis-label">Headers</label>
                            <div class="splis-ob-table-headers flex flex-wrap gap-2">${headerInputs}</div>
                        </div>
                        <div>
                            <label class="splis-label">Rows</label>
                            <div class="splis-ob-table-rows space-y-2">${rowHtml}</div>
                        </div>
                        ${canEdit ? '<button type="button" class="splis-btn-secondary splis-ob-add-row" data-block-id="' + block.id + '">Add row</button>' : ''}
                    </div>
                `;
            }
            case 'page_break':
                return '<p class="text-sm text-slate-500 italic">Page break — content after this block starts on a new page when printing.</p>';
            default:
                return `<p class="text-sm text-slate-500">${escapeHtml(block.preview ?? '')}</p>`;
        }
    }

    function renderBlocks() {
        if (!blocksList || !blocksEmpty) {
            return;
        }

        if (blocks.length === 0) {
            blocksList.innerHTML = '';
            blocksEmpty.classList.remove('hidden');
            return;
        }

        blocksEmpty.classList.add('hidden');
        blocksList.innerHTML = blocks
            .map((block, index) => {
                const selected = block.id === selectedBlockId ? ' is-selected' : '';
                return `
                    <article class="splis-ob-block${selected}" data-block-id="${block.id}">
                        <div class="splis-ob-block-head">
                            <span class="splis-ob-block-order">${block.sort_order}</span>
                            <span class="splis-ob-block-type">${escapeHtml(block.type_label)}</span>
                            ${renderBlockControls(block, index)}
                        </div>
                        <div class="splis-ob-block-body">${renderBlockEditor(block)}</div>
                    </article>
                `;
            })
            .join('');
    }

    function collectBlockContent(blockEl, block) {
        const content = { ...(block.content ?? {}) };

        if (block.type === 'table') {
            const headers = [...blockEl.querySelectorAll('.splis-ob-table-header')].map((input) => input.value);
            const rowEls = [...blockEl.querySelectorAll('.splis-ob-table-row')];
            content.headers = headers;
            content.rows = rowEls.map((rowEl) =>
                [...rowEl.querySelectorAll('.splis-ob-table-cell')].map((input) => input.value),
            );
            return content;
        }

        blockEl.querySelectorAll('.splis-ob-block-field').forEach((field) => {
            const key = field.dataset.field;
            if (!key) {
                return;
            }
            if (key === 'row_no') {
                content[key] = field.value === '' ? null : Number(field.value);
            } else if (key === 'numeral') {
                content[key] = storeRomanNumeral(field.value);
            } else if (key === 'committee_id') {
                const option = field.selectedOptions[0];
                content.committee_id = field.value === '' ? null : Number(field.value);
                content.committee_name = option?.dataset.committeeName ?? '';
            } else {
                content[key] = field.value;
            }
        });

        return content;
    }

    function syncBlockFromDom(blockId) {
        const block = blocks.find((item) => item.id === blockId);
        const blockEl = blocksList?.querySelector(`[data-block-id="${blockId}"]`);
        if (!block || !blockEl) {
            return;
        }
        block.content = collectBlockContent(blockEl, block);
    }

    const saveBlock = debounce(async (blockId) => {
        const block = blocks.find((item) => item.id === blockId);
        const blockEl = blocksList?.querySelector(`[data-block-id="${blockId}"]`);
        if (!block || !blockEl || !canEdit) {
            return;
        }

        try {
            setStatus('Saving…');
            const content = collectBlockContent(blockEl, block);
            const data = await api(blockUrl(urls.updateBlock, blockId), {
                method: 'PUT',
                body: JSON.stringify({ content }),
            });
            if (data.blocks) {
                blocks = normalizeBlocks(data.blocks).sort((a, b) => a.sort_order - b.sort_order);
                const updated = blocks.find((item) => item.id === blockId);
                if (updated) {
                    selectedBlockId = updated.id;
                }
                renderBlocks();
            } else {
                const index = blocks.findIndex((item) => item.id === blockId);
                blocks[index] = normalizeRomanBlock(data.block);
                const numeralField = blockEl.querySelector('[data-field="numeral"]');
                if (numeralField) {
                    numeralField.value = blocks[index].content?.numeral ?? '';
                }
            }
            setStatus('Saved');
        } catch (error) {
            setStatus(error.message, true);
        }
    }, 600);

    async function persistOrder() {
        const order = blocks.map((block) => block.id);
        const data = await api(urls.reorder, {
            method: 'PUT',
            body: JSON.stringify({ order }),
        });
        blocks = normalizeBlocks(data.blocks).sort((a, b) => a.sort_order - b.sort_order);
        renderBlocks();
    }

    async function addBlock(type, afterBlockId = null) {
        try {
            setStatus('Adding block…');
            const data = await api(urls.storeBlock, {
                method: 'POST',
                body: JSON.stringify({
                    type,
                    after_block_id: afterBlockId ?? selectedBlockId,
                }),
            });
            blocks.push(normalizeRomanBlock(data.block));
            if (data.blocks) {
                blocks = normalizeBlocks(data.blocks).sort((a, b) => a.sort_order - b.sort_order);
            } else {
                blocks.sort((a, b) => a.sort_order - b.sort_order);
            }
            selectedBlockId = data.block.id;
            documentState = data.document;
            renderBlocks();
            setStatus('Block added');
        } catch (error) {
            setStatus(error.message, true);
        }
    }

    const confirmDialog = document.getElementById('ob-confirm-dialog');
    const confirmTitleEl = document.getElementById('ob-confirm-title');
    const confirmMessageEl = document.getElementById('ob-confirm-message');
    const confirmOkBtn = document.getElementById('ob-confirm-ok');
    let confirmResolve = null;

    function closeConfirmDialog(result) {
        if (!confirmDialog) {
            return;
        }
        confirmDialog.classList.remove('is-open');
        confirmDialog.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('splis-ob-dialog-open');
        if (confirmResolve) {
            confirmResolve(result);
            confirmResolve = null;
        }
    }

    function confirmAction({ title, message, confirmLabel = 'Delete' }) {
        if (!confirmDialog || !confirmTitleEl || !confirmMessageEl || !confirmOkBtn) {
            return Promise.resolve(window.confirm(message));
        }

        return new Promise((resolve) => {
            confirmResolve = resolve;
            confirmTitleEl.textContent = title;
            confirmMessageEl.textContent = message;
            confirmOkBtn.textContent = confirmLabel;
            confirmDialog.classList.add('is-open');
            confirmDialog.setAttribute('aria-hidden', 'false');
            document.body.classList.add('splis-ob-dialog-open');
            confirmOkBtn.focus();
        });
    }

    if (confirmDialog) {
        confirmDialog.querySelectorAll('[data-ob-confirm-cancel]').forEach((el) => {
            el.addEventListener('click', () => closeConfirmDialog(false));
        });
        confirmOkBtn?.addEventListener('click', () => closeConfirmDialog(true));
        confirmDialog.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeConfirmDialog(false);
            }
        });
    }

    async function deleteBlock(blockId) {
        const block = blocks.find((item) => item.id === blockId);
        const label = block?.type_label ?? 'this block';
        const confirmed = await confirmAction({
            title: 'Delete block?',
            message: `Remove ${label} from the document? This cannot be undone.`,
            confirmLabel: 'Delete',
        });
        if (!confirmed) {
            return;
        }

        try {
            setStatus('Deleting…');
            const deletedBlock = blocks.find((block) => block.id === blockId);
            const data = await api(blockUrl(urls.deleteBlock, blockId), { method: 'DELETE' });
            blocks = normalizeBlocks(data.blocks ?? blocks.filter((block) => block.id !== blockId)).sort((a, b) => a.sort_order - b.sort_order);
            if (selectedBlockId === blockId) {
                selectedBlockId = blocks[0]?.id ?? null;
            }
            documentState = data.document;
            renderBlocks();
            if (deletedBlock?.agenda_item_id) {
                loadAgendaPool(1, false);
            }
            setStatus('Block deleted');
        } catch (error) {
            setStatus(error.message, true);
        }
    }

    function moveBlock(blockId, direction) {
        if (selectedBlockId !== null) {
            syncBlockFromDom(selectedBlockId);
        }
        syncBlockFromDom(blockId);

        const index = blocks.findIndex((block) => block.id === blockId);
        const target = index + direction;
        if (index < 0 || target < 0 || target >= blocks.length) {
            return;
        }

        const copy = [...blocks];
        [copy[index], copy[target]] = [copy[target], copy[index]];
        blocks = copy.map((block, sortIndex) => ({ ...block, sort_order: sortIndex + 1 }));
        renderBlocks();
        persistOrder().catch((error) => setStatus(error.message, true));
    }

    async function loadAgendaPool(page = 1, append = false) {
        if (!agendaPoolEl || poolLoading) {
            return;
        }

        if (!append) {
            disconnectPoolObserver();
            poolPage = page;
            poolItems = [];
            agendaPoolEl.innerHTML = '<p class="text-sm text-slate-500">Loading…</p>';
        } else {
            renderAgendaPool(true);
        }

        poolLoading = true;

        const params = new URLSearchParams({ page: String(page) });
        const query = agendaSearchInput?.value?.trim() ?? '';
        if (query !== '') {
            params.set('q', query);
        }

        try {
            const data = await api(`${urls.agendaPool}?${params.toString()}`);
            poolMeta = data.meta;
            poolPage = data.meta.current_page;

            if (append) {
                const existing = new Set(poolItems.map((item) => item.id));
                const newItems = data.data.filter((item) => !existing.has(item.id));
                poolItems = [...poolItems, ...newItems];
            } else {
                poolItems = data.data;
            }

            poolLoading = false;
            renderAgendaPool();
        } catch (error) {
            poolLoading = false;
            if (!append) {
                disconnectPoolObserver();
                agendaPoolEl.innerHTML = `<p class="text-sm text-red-600">${escapeHtml(error.message)}</p>`;
            } else {
                renderAgendaPool();
            }
        }
    }

    function disconnectPoolObserver() {
        poolObserver?.disconnect();
        poolObserver = null;
    }

    function loadMoreAgendaItems() {
        if (poolLoading || poolPage >= poolMeta.last_page) {
            return;
        }

        loadAgendaPool(poolPage + 1, true);
    }

    function observePoolSentinel() {
        disconnectPoolObserver();

        if (!agendaPoolEl) {
            return;
        }

        const sentinel = document.getElementById('ob-agenda-pool-sentinel');
        if (!sentinel) {
            return;
        }

        poolObserver = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    loadMoreAgendaItems();
                }
            },
            { root: agendaPoolEl, rootMargin: '48px', threshold: 0 },
        );
        poolObserver.observe(sentinel);
    }

    function fillPoolIfShort() {
        if (!agendaPoolEl || poolLoading || poolPage >= poolMeta.last_page) {
            return;
        }

        if (agendaPoolEl.scrollHeight <= agendaPoolEl.clientHeight + 8) {
            loadMoreAgendaItems();
        }
    }

    function schedulePoolFillCheck() {
        requestAnimationFrame(() => {
            observePoolSentinel();
            fillPoolIfShort();
        });
    }

    function renderAgendaItemMarkup(item) {
        const checked = selectedAgendaIds.has(item.id) ? 'checked' : '';
        const { display, full, truncated } = truncateWords(item.title ?? 'Untitled', 23);

        return `
            <label class="splis-ob-agenda-item">
                <input type="checkbox" value="${item.id}" ${checked}>
                <span class="min-w-0 flex-1">
                    <strong>${escapeHtml(item.label)}</strong>
                    <span class="block text-xs text-slate-500">${renderTruncatedTitle(display, full, truncated)}</span>
                    <span class="block text-xs text-slate-400">${escapeHtml(item.sender ?? '')}${item.date_received_display ? ' · ' + escapeHtml(item.date_received_display) : ''}${item.committee_referred ? ' · ' + escapeHtml(item.committee_referred) : ''}</span>
                </span>
            </label>
        `;
    }

    function renderAgendaPool(loadingMore = false) {
        if (!agendaPoolEl) {
            return;
        }

        if (poolItems.length === 0 && !poolLoading && !loadingMore) {
            disconnectPoolObserver();
            agendaPoolEl.innerHTML = '<p class="text-sm text-slate-500">No agenda items found.</p>';
            return;
        }

        const itemsHtml = poolItems.map((item) => renderAgendaItemMarkup(item)).join('');
        const hasMore = poolPage < poolMeta.last_page;
        let footer = '';

        if (poolLoading || loadingMore) {
            footer = '<p class="py-2 text-center text-xs text-slate-500">Loading more…</p>';
        } else if (hasMore) {
            footer = '<p id="ob-agenda-pool-sentinel" class="py-2 text-center text-xs text-slate-400">Scroll for more</p>';
        } else if (poolItems.length > 0) {
            footer = `<p class="py-2 text-center text-xs text-slate-400">${poolMeta.total} item(s)</p>`;
        }

        agendaPoolEl.innerHTML = itemsHtml + footer;
        bindTitleTooltips(agendaPoolEl);

        if (!poolLoading && !loadingMore) {
            schedulePoolFillCheck();
        }
    }

    function insertAfterBlockIdForAnnouncements(romanBlockId) {
        const startIndex = blocks.findIndex((item) => item.id === romanBlockId);
        if (startIndex < 0) {
            return romanBlockId;
        }

        let afterId = romanBlockId;
        for (let i = startIndex + 1; i < blocks.length; i++) {
            const block = blocks[i];
            if (block.type === 'announcement') {
                afterId = block.id;
                continue;
            }
            if (block.type === 'adjournment') {
                break;
            }
            if (block.type === 'roman_section' && !isAnnouncementsSection(block.content ?? {})) {
                break;
            }
        }

        return afterId;
    }

    async function addAnnouncementRow(romanBlockId) {
        const afterId = insertAfterBlockIdForAnnouncements(romanBlockId);
        await addBlock('announcement', afterId);
    }

    async function addSelectedAgenda() {
        if (selectedAgendaIds.size === 0) {
            setStatus('Select agenda items first.', true);
            return;
        }

        const section = agendaSectionSelect?.value ?? 'unassigned_regular';

        try {
            setStatus('Adding agenda items…');
            const data = await api(urls.fromAgenda, {
                method: 'POST',
                body: JSON.stringify({
                    agenda_item_ids: [...selectedAgendaIds],
                    section,
                }),
            });

            blocks = normalizeBlocks(data.all_blocks ?? data.blocks ?? blocks).sort((a, b) => a.sort_order - b.sort_order);
            const firstAddedId = data.blocks?.[0]?.id ?? null;
            selectedBlockId = firstAddedId ?? blocks[blocks.length - 1]?.id ?? selectedBlockId;
            documentState = data.document;
            selectedAgendaIds.clear();
            renderBlocks();
            if (firstAddedId) {
                blocksList
                    ?.querySelector(`[data-block-id="${firstAddedId}"]`)
                    ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            loadAgendaPool(1, false);
            const sectionLabel =
                config.agendaSections?.find((item) => item.value === agendaSectionSelect?.value)?.label ?? 'section';
            setStatus(`${data.blocks.length} agenda item(s) added to ${sectionLabel}`);
        } catch (error) {
            setStatus(error.message, true);
        }
    }

    const saveDocumentMeta = debounce(async () => {
        if (!canEdit) {
            return;
        }

        try {
            setStatus('Saving document…');
            const data = await api(urls.updateDocument, {
                method: 'PUT',
                body: JSON.stringify({
                    title: titleInput?.value ?? documentState.title,
                    status: statusSelect?.value ?? documentState.status,
                }),
            });
            documentState = data.document;
            setStatus('Document saved');
        } catch (error) {
            setStatus(error.message, true);
        }
    }, 500);

    root.addEventListener('click', (event) => {
        const target = event.target;

        if (target.matches('[data-add-type]')) {
            addBlock(target.dataset.addType);
            return;
        }

        if (target.matches('.splis-ob-add-announcement')) {
            addAnnouncementRow(Number(target.dataset.afterBlock));
            return;
        }

        if (target.matches('#ob-add-selected-agenda')) {
            addSelectedAgenda();
            return;
        }

        if (target.matches('[data-move-up]')) {
            moveBlock(Number(target.dataset.moveUp), -1);
            return;
        }

        if (target.matches('[data-move-down]')) {
            moveBlock(Number(target.dataset.moveDown), 1);
            return;
        }

        if (target.matches('[data-delete-block]')) {
            deleteBlock(Number(target.dataset.deleteBlock));
            return;
        }

        if (target.matches('.splis-ob-add-row')) {
            const blockId = Number(target.dataset.blockId);
            const block = blocks.find((item) => item.id === blockId);
            if (!block) {
                return;
            }
            const headers = block.content?.headers ?? [];
            block.content.rows = [...(block.content.rows ?? []), headers.map(() => '')];
            renderBlocks();
            saveBlock(blockId);
            return;
        }

        if (target.matches('[data-remove-row]')) {
            const blockEl = target.closest('[data-block-id]');
            const blockId = Number(blockEl?.dataset.blockId);
            const block = blocks.find((item) => item.id === blockId);
            const rowIndex = Number(target.dataset.removeRow);
            if (!block) {
                return;
            }
            block.content.rows.splice(rowIndex, 1);
            renderBlocks();
            saveBlock(blockId);
            return;
        }

        if (target.closest('input, textarea, select, label')) {
            return;
        }

        const blockArticle = target.closest('[data-block-id]');
        if (blockArticle) {
            const newId = Number(blockArticle.dataset.blockId);
            if (newId !== selectedBlockId) {
                if (selectedBlockId !== null) {
                    syncBlockFromDom(selectedBlockId);
                }
                selectedBlockId = newId;
                renderBlocks();
            }
        }
    });

    root.addEventListener('blur', (event) => {
        const target = event.target;
        if (!target.matches('.splis-ob-block-field[data-field="numeral"]')) {
            return;
        }

        const formatted = storeRomanNumeral(target.value);
        if (target.value === formatted) {
            return;
        }

        target.value = formatted;
        const blockEl = target.closest('[data-block-id]');
        if (blockEl) {
            syncBlockFromDom(Number(blockEl.dataset.blockId));
        }
    }, true);

    root.addEventListener('input', (event) => {
        const target = event.target;
        const blockEl = target.closest('[data-block-id]');
        if (!blockEl) {
            return;
        }
        const blockId = Number(blockEl.dataset.blockId);
        syncBlockFromDom(blockId);
        saveBlock(blockId);
    });

    root.addEventListener('change', (event) => {
        const target = event.target;
        if (target.matches('#ob-doc-title, #ob-doc-status')) {
            saveDocumentMeta();
            return;
        }

        if (target.matches('.splis-ob-block-field')) {
            const blockEl = target.closest('[data-block-id]');
            if (blockEl) {
                const blockId = Number(blockEl.dataset.blockId);
                if (target.dataset.field === 'committee_id') {
                    const committee = committees.find((item) => String(item.id) === target.value);
                    const chairField = blockEl.querySelector('[data-field="chair_name"]');
                    if (committee?.chair && chairField) {
                        chairField.value = committee.chair;
                    }
                }
                syncBlockFromDom(blockId);
                saveBlock(blockId);
            }
            return;
        }

        if (target.matches('.splis-ob-agenda-item input[type="checkbox"]')) {
            const id = Number(target.value);
            if (target.checked) {
                selectedAgendaIds.add(id);
            } else {
                selectedAgendaIds.delete(id);
            }
        }
    });

    if (agendaSearchInput) {
        agendaSearchInput.addEventListener(
            'input',
            debounce(() => loadAgendaPool(1, false), 300),
        );
    }

    if (agendaSectionSelect) {
        agendaSectionSelect.addEventListener('change', () => {
            selectedAgendaIds.clear();
        });
    }

    renderAddBlockButtons();
    renderBlocks();
    if (canEdit) {
        loadAgendaPool(1, false);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initObMaker();
});
