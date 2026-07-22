import { bindTitleTooltips, renderTruncatedTitle, truncateWords } from './title-tooltip';

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function sanitizeRichTitleHtml(html) {
    const template = document.createElement('template');
    template.innerHTML = String(html ?? '');

    function sanitizeNode(node) {
        if (node.nodeType === Node.TEXT_NODE) {
            return escapeHtml(node.textContent ?? '');
        }

        if (node.nodeType !== Node.ELEMENT_NODE) {
            return '';
        }

        const tag = node.tagName.toLowerCase();
        const children = [...node.childNodes].map(sanitizeNode).join('');

        if (tag === 'strong' || tag === 'b') {
            return `<strong>${children}</strong>`;
        }

        if (tag === 'mark') {
            return `<mark>${children}</mark>`;
        }

        if ((tag === 'span' || tag === 'font') && elementIsHighlight(node)) {
            return `<mark>${children}</mark>`;
        }

        if (tag === 'br') {
            return '<br>';
        }

        if (tag === 'div' || tag === 'p') {
            return `${children}<br>`;
        }

        return children;
    }

    return [...template.content.childNodes]
        .map(sanitizeNode)
        .join('')
        .replace(/(?:<br>){3,}/g, '<br><br>')
        .replace(/^(?:<br>)+|(?:<br>)+$/g, '');
}

function richTitlePlainText(html) {
    const template = document.createElement('template');
    template.innerHTML = sanitizeRichTitleHtml(html).replace(/<br>/g, '\n');

    return (template.content.textContent ?? '').replace(/\u00a0/g, ' ').trim();
}

function richTitleHtmlForContent(content) {
    const title = String(content.title ?? '');
    const formatted = sanitizeRichTitleHtml(content.title_html ?? '');

    if (formatted && richTitlePlainText(formatted).replace(/\s+/g, ' ') === title.trim().replace(/\s+/g, ' ')) {
        return formatted;
    }

    return escapeHtml(title).replace(/\r?\n/g, '<br>');
}

function elementIsHighlight(node) {
    if (!node || node.nodeType !== Node.ELEMENT_NODE) {
        return false;
    }

    if (node.nodeName === 'MARK') {
        return true;
    }

    const background = node.style?.backgroundColor ?? '';

    return background !== ''
        && background !== 'transparent'
        && background !== 'rgba(0, 0, 0, 0)';
}

function selectionHasHighlight(editor) {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
        return false;
    }

    let node = selection.getRangeAt(0).commonAncestorContainer;
    if (node.nodeType === Node.TEXT_NODE) {
        node = node.parentNode;
    }

    while (node && node !== editor) {
        if (elementIsHighlight(node)) {
            return true;
        }
        node = node.parentNode;
    }

    return false;
}

function removeHighlightFromSelection(editor) {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
        return;
    }

    const range = selection.getRangeAt(0);
    const highlights = [...editor.querySelectorAll('mark, span, font')]
        .filter((el) => elementIsHighlight(el) && (range.intersectsNode(el) || el.contains(range.commonAncestorContainer)));

    highlights.forEach((el) => {
        if (el.nodeName === 'MARK' || el.nodeName === 'FONT') {
            const parent = el.parentNode;
            while (el.firstChild) {
                parent.insertBefore(el.firstChild, el);
            }
            parent.removeChild(el);
        } else {
            el.style.backgroundColor = '';
            if (el.getAttribute('style')?.trim() === '') {
                el.removeAttribute('style');
            }
        }
    });

    editor.normalize();
}

function renderRichTitleEditor(content, disabled) {
    return `
        <div class="splis-ob-rich-title-wrap">
            <div class="splis-ob-rich-title-toolbar" role="toolbar" aria-label="Title formatting">
                <button
                    type="button"
                    class="splis-ob-rich-title-button"
                    data-ob-rich-command="bold"
                    title="Bold selected text"
                    aria-label="Bold selected text"
                    ${disabled}
                ><strong>B</strong></button>
                <button
                    type="button"
                    class="splis-ob-rich-title-button splis-ob-rich-title-button--highlight"
                    data-ob-rich-command="hiliteColor"
                    data-ob-rich-value="#fef08a"
                    title="Highlight selected text"
                    aria-label="Highlight selected text"
                    ${disabled}
                ><strong>H</strong></button>
            </div>
            <div
                class="splis-ob-rich-title"
                contenteditable="${disabled ? 'false' : 'true'}"
                role="textbox"
                aria-multiline="true"
                data-rich-title
            >${richTitleHtmlForContent(content)}</div>
            <p class="mt-1 text-xs text-slate-500">Select words, then click B for bold or H to highlight.</p>
        </div>
    `;
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
    const sectionNavEl = document.getElementById('ob-section-nav');
    const sectionNavList = document.getElementById('ob-section-nav-list');
    const sectionNavToggle = document.getElementById('ob-section-nav-toggle');
    const sectionNavPanel = document.getElementById('ob-section-nav-panel');
    const sectionNavExpandToggleBtn = document.getElementById('ob-section-nav-expand-toggle');
    const sectionNavCloseBtn = document.getElementById('ob-section-nav-close');
    const sectionNavDragHandle = document.getElementById('ob-section-nav-drag-handle');
    const sectionNavResizeHandle = document.getElementById('ob-section-nav-resize');
    let sectionNavObserver = null;
    /** @type {Set<number>|null} null = expand every parent by default */
    let sectionNavExpandedIds = null;
    const SECTION_NAV_LAYOUT_KEY = 'splis-ob-section-nav-layout';
    let sectionNavDragState = null;
    let sectionNavResizeState = null;
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
                        <option value="urgent" ${c.kind === 'urgent' ? 'selected' : ''}>Urgent Request</option>
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
                    ${renderRichTitleEditor(c, disabled)}
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

    function renderSectionMoveDropdown(block) {
        if (!canEdit || !block.can_move_section) {
            return '';
        }

        const sections = config.agendaSections ?? [];
        const options = sections
            .filter((item) => item.value !== block.section)
            .map(
                (item) =>
                    `<option value="${escapeHtml(item.value)}">${escapeHtml(item.label)}</option>`,
            )
            .join('');

        return `
            <div class="splis-ob-section-move mb-3 border-b border-slate-200 pb-3 dark:border-slate-700">
                <label class="splis-label">Move to section</label>
                <p class="mb-1 text-xs text-slate-500">Currently in: ${escapeHtml(block.section_label ?? block.section ?? '')}</p>
                <select class="splis-select splis-ob-move-section" data-block-id="${block.id}">
                    <option value="">Select section…</option>
                    ${options}
                </select>
            </div>
        `;
    }

    function renderSectionThreeHint(c) {
        const sectionThree = config.sectionThree ?? {};

        if (sectionThree.prior_session_title) {
            return `<p class="mt-1 text-xs text-slate-500">Based on ${escapeHtml(sectionThree.prior_session_title)}. Journal and Minutes URLs auto-fill from that session unless you override them below. JOURNAL and MINUTES are linked in print preview.</p>`;
        }

        return '<p class="mt-1 text-xs text-slate-500">No prior session set. Enter Journal and Minutes PDF URLs below to link JOURNAL and MINUTES in print preview.</p>';
    }

    function renderSectionThreeLinkFields(c, disabled) {
        if (normalizeRomanNumeral(c.numeral) !== 'III') {
            return '';
        }

        return `
            <div>
                <label class="splis-label">Journal PDF URL</label>
                <input type="url" class="splis-input splis-ob-block-field" data-field="journal_url" value="${escapeHtml(c.journal_url ?? '')}" ${disabled} placeholder="https://">
            </div>
            <div>
                <label class="splis-label">Minutes PDF URL</label>
                <input type="url" class="splis-input splis-ob-block-field" data-field="minutes_url" value="${escapeHtml(c.minutes_url ?? '')}" ${disabled} placeholder="https://">
            </div>
        `;
    }

    function renderBlockEditor(block) {
        const c = block.content ?? {};
        const disabled = canEdit ? '' : 'disabled';
        const sectionMove = renderSectionMoveDropdown(block);

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
                            ${normalizeRomanNumeral(c.numeral) === 'III' ? renderSectionThreeHint(c) : ''}
                        </div>
                    `
                    : '';
                const sectionThreeLinkFields = renderSectionThreeLinkFields(c, disabled);
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
                        ${sectionThreeLinkFields}
                        ${subLabelField}
                        ${announcementActions}
                    </div>
                `;
            }
            case 'paragraph':
                return `<textarea class="splis-textarea splis-ob-block-field" data-field="text" rows="4" ${disabled}>${escapeHtml(c.text ?? '')}</textarea>`;
            case 'committee_report':
                return `
                    ${sectionMove}
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
                return sectionMove + renderAgendaMetaFields(c, disabled, { includeCommittee: c.needs_committee === true });
            case 'unassigned_agenda':
                return sectionMove + renderAgendaMetaFields(c, disabled, {
                    includeKind: true,
                    referralNotePlaceholder:
                        (c.kind ?? 'regular') === 'urgent'
                            ? 'Sponsored by: SP Committee on …\nChaired by: Board Member …'
                            : '(To be referred to SP Committee on …, Chaired by: … )',
                });
            case 'reading_agenda':
                return `
                    ${sectionMove}
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
                            ${renderRichTitleEditor(c, disabled)}
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

    function romanSectionNavLabel(c) {
        const numeral = displayRomanNumeral(c.numeral);
        const title = String(c.title ?? '').trim();
        if (title) {
            return `${numeral} ${title}`.trim();
        }

        const body = String(c.body ?? '').trim();
        const firstLine = body.split(/\r?\n/)[0]?.trim() ?? '';
        if (firstLine) {
            return `${numeral} ${firstLine}`.trim();
        }

        return numeral || 'Section';
    }

    function subsectionNavLevel(text) {
        const trimmed = String(text ?? '').trim();
        if (/^\d+\./.test(trimmed)) {
            return 2;
        }
        if (/^[A-Z]\./i.test(trimmed)) {
            return 1;
        }

        return 1;
    }

    function agendaNosLabel(c) {
        if (Array.isArray(c.agenda_nos) && c.agenda_nos.length > 0) {
            return c.agenda_nos.map((no) => String(no).trim()).filter(Boolean).join(', ');
        }

        const no = c.agenda_no ?? c.session_agenda_no;
        return no !== null && no !== undefined && String(no).trim() !== '' ? String(no).trim() : '';
    }

    function limitNavWords(text, maxWords = 10) {
        return truncateWords(text, maxWords).display;
    }

    function formatAgendaNavLabel(nos, title = '') {
        const number = String(nos ?? '')
            .trim()
            .replace(/^agenda\s+/i, '')
            .replace(/\.$/, '');
        const titleLimited = limitNavWords(String(title ?? '').trim(), 10);

        if (number && titleLimited && titleLimited !== '—') {
            return `Agenda ${number}. ${titleLimited}`;
        }
        if (number) {
            return `Agenda ${number}`;
        }
        if (titleLimited && titleLimited !== '—') {
            return /^agenda\b/i.test(titleLimited) ? titleLimited : `Agenda ${titleLimited}`;
        }

        return 'Agenda item';
    }

    function agendaItemNavLabel(block) {
        const c = block.content ?? {};

        switch (block.type) {
            case 'committee_report': {
                const row = c.row_no !== null && c.row_no !== undefined && c.row_no !== '' ? `${c.row_no}. ` : '';
                const nos = agendaNosLabel(c);
                const committee = String(c.committee_name ?? '').trim();
                return `${row}${formatAgendaNavLabel(nos, committee)}`.trim();
            }
            case 'unfinished_committee':
                return limitNavWords(String(c.committee_name ?? '').trim() || 'Committee', 10);
            case 'unfinished_agenda':
            case 'unassigned_agenda':
            case 'reading_agenda':
            case 'agenda_line': {
                const nos = agendaNosLabel(c);
                const title = String(c.title ?? '').trim();
                const reading = block.type === 'reading_agenda' && c.reading ? `${c.reading} reading — ` : '';
                return `${reading}${formatAgendaNavLabel(nos, title)}`.trim();
            }
            case 'announcement': {
                const col2 = String(c.column_2 ?? c.title ?? '').trim();
                const col1 = String(c.column_1 ?? c.date_received ?? '').trim();
                if (col2 && col1) {
                    return limitNavWords(`${col1} — ${col2}`, 10);
                }
                return limitNavWords(col2 || col1 || block.preview || 'Announcement', 10);
            }
            default:
                return limitNavWords(String(block.preview ?? block.type_label ?? 'Item').trim(), 10);
        }
    }

    function sectionNavEntries() {
        const entries = [];
        const agendaTypes = new Set([
            'committee_report',
            'unfinished_committee',
            'unfinished_agenda',
            'unassigned_agenda',
            'reading_agenda',
            'agenda_line',
            'announcement',
        ]);

        for (const block of blocks) {
            const c = block.content ?? {};

            if (block.type === 'roman_section') {
                entries.push({
                    blockId: block.id,
                    level: 0,
                    kind: 'section',
                    label: limitNavWords(romanSectionNavLabel(c), 10),
                });
                continue;
            }

            if (block.type === 'subsection_label') {
                const label = String(c.text ?? '').trim();
                if (label !== '') {
                    entries.push({
                        blockId: block.id,
                        level: subsectionNavLevel(label),
                        kind: 'subsection',
                        label: limitNavWords(label, 10),
                    });
                }
                continue;
            }

            if (block.type === 'adjournment') {
                entries.push({
                    blockId: block.id,
                    level: 0,
                    kind: 'section',
                    label: limitNavWords(String(block.preview ?? 'VIII. ADJOURNMENT').trim() || 'VIII. ADJOURNMENT', 10),
                });
                continue;
            }

            if (block.type === 'unfinished_committee') {
                entries.push({
                    blockId: block.id,
                    level: 2,
                    kind: 'committee',
                    label: agendaItemNavLabel(block),
                });
                continue;
            }

            if (agendaTypes.has(block.type)) {
                entries.push({
                    blockId: block.id,
                    level: 3,
                    kind: 'agenda',
                    label: agendaItemNavLabel(block),
                });
            }
        }

        return entries;
    }

    function buildSectionNavTree(entries) {
        const roots = [];
        /** @type {Array<any>} */
        const parentsByLevel = [];

        for (const entry of entries) {
            const node = { ...entry, children: [] };
            const level = entry.level;

            if (level === 0) {
                roots.push(node);
                parentsByLevel.length = 0;
                parentsByLevel[0] = node;
                continue;
            }

            let parent = null;
            for (let parentLevel = level - 1; parentLevel >= 0; parentLevel -= 1) {
                if (parentsByLevel[parentLevel]) {
                    parent = parentsByLevel[parentLevel];
                    break;
                }
            }

            if (parent) {
                parent.children.push(node);
            } else {
                roots.push(node);
            }

            parentsByLevel[level] = node;
            parentsByLevel.length = level + 1;
        }

        return roots;
    }

    function flattenSectionNavTree(nodes, out = []) {
        for (const node of nodes) {
            out.push(node);
            if (node.children?.length) {
                flattenSectionNavTree(node.children, out);
            }
        }

        return out;
    }

    function sectionNavParentIds(tree) {
        return flattenSectionNavTree(tree)
            .filter((node) => node.children.length > 0)
            .map((node) => node.blockId);
    }

    function findSectionNavPath(nodes, blockId, path = []) {
        const targetId = Number(blockId);
        for (const node of nodes) {
            const nextPath = [...path, Number(node.blockId)];
            if (Number(node.blockId) === targetId) {
                return nextPath;
            }
            if (node.children.length > 0) {
                const found = findSectionNavPath(node.children, targetId, nextPath);
                if (found) {
                    return found;
                }
            }
        }

        return null;
    }

    function isSectionNavExpanded(blockId) {
        if (sectionNavExpandedIds === null) {
            return true;
        }

        return sectionNavExpandedIds.has(Number(blockId));
    }

    function ensureSectionNavAncestorsExpanded(tree, blockId) {
        const path = findSectionNavPath(tree, Number(blockId));
        if (!path || path.length < 2) {
            return false;
        }

        const parentIds = path.slice(0, -1).map(Number);
        if (sectionNavExpandedIds === null) {
            return false;
        }

        let changed = false;
        parentIds.forEach((id) => {
            if (!sectionNavExpandedIds.has(id)) {
                sectionNavExpandedIds.add(id);
                changed = true;
            }
        });

        return changed;
    }

    function applySectionNavExpandedState(expanded) {
        if (expanded) {
            // null = every parent treated as expanded
            sectionNavExpandedIds = null;
        } else {
            sectionNavExpandedIds = new Set();
        }
        renderSectionNav();
        syncSectionNavExpandToggle();
    }

    function sectionNavParentsAreExpanded() {
        if (sectionNavExpandedIds === null) {
            return true;
        }

        const parentIds = sectionNavParentIds(buildSectionNavTree(sectionNavEntries())).map(Number);
        if (parentIds.length === 0) {
            return true;
        }

        return parentIds.every((id) => sectionNavExpandedIds.has(id));
    }

    function syncSectionNavExpandToggle() {
        if (!sectionNavExpandToggleBtn) {
            return;
        }

        const expanded = sectionNavParentsAreExpanded();
        sectionNavExpandToggleBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        sectionNavExpandToggleBtn.title = expanded ? 'Collapse all' : 'Expand all';
        sectionNavExpandToggleBtn.setAttribute(
            'aria-label',
            expanded ? 'Collapse all sections' : 'Expand all sections',
        );
        sectionNavExpandToggleBtn.classList.toggle('is-expanded', expanded);
        sectionNavExpandToggleBtn.innerHTML = expanded
            ? `
                <svg class="splis-ob-section-nav-expand-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M14.77 12.79a.75.75 0 01-1.06-.02L10 8.832 6.29 12.77a.75.75 0 11-1.08-1.04l4.25-4.5a.75.75 0 011.08 0l4.25 4.5a.75.75 0 01-.02 1.06z" clip-rule="evenodd"/>
                </svg>
            `
            : `
                <svg class="splis-ob-section-nav-expand-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                </svg>
            `;
    }

    function teardownSectionNavObserver() {
        sectionNavObserver?.disconnect();
        sectionNavObserver = null;
    }

    function setActiveSectionNavLink(blockId) {
        if (!sectionNavList) {
            return;
        }

        sectionNavList.querySelectorAll('.splis-ob-section-nav-link').forEach((link) => {
            link.classList.toggle('is-active', Number(link.dataset.blockId) === blockId);
        });
    }

    function setupSectionNavObserver(entries, tree) {
        teardownSectionNavObserver();

        if (!sectionNavList || entries.length === 0) {
            return;
        }

        const blockIds = new Set(entries.map((entry) => entry.blockId));
        const visible = new Map();

        sectionNavObserver = new IntersectionObserver(
            (observed) => {
                observed.forEach((record) => {
                    const id = Number(record.target.dataset.blockId);
                    if (!blockIds.has(id)) {
                        return;
                    }
                    if (record.isIntersecting) {
                        visible.set(id, record.intersectionRatio);
                    } else {
                        visible.delete(id);
                    }
                });

                if (visible.size === 0) {
                    return;
                }

                let bestId = null;
                let bestRatio = -1;
                visible.forEach((ratio, id) => {
                    if (ratio > bestRatio) {
                        bestRatio = ratio;
                        bestId = id;
                    }
                });

                if (bestId === null) {
                    return;
                }

                if (ensureSectionNavAncestorsExpanded(tree, bestId)) {
                    renderSectionNav();
                    return;
                }

                setActiveSectionNavLink(bestId);
            },
            {
                root: null,
                rootMargin: '-20% 0px -55% 0px',
                threshold: [0, 0.1, 0.25, 0.5, 0.75, 1],
            },
        );

        entries.forEach((entry) => {
            const el = document.getElementById(`ob-block-${entry.blockId}`);
            if (el) {
                sectionNavObserver.observe(el);
            }
        });
    }

    function scrollToObBlock(blockId) {
        const el = document.getElementById(`ob-block-${blockId}`);
        if (!el) {
            return;
        }

        const offset = 112;
        const top = el.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    }

    function sectionNavCaretHtml() {
        return `
            <svg class="splis-ob-section-nav-caret-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
            </svg>
        `;
    }

    function renderSectionNavNode(node) {
        const hasChildren = node.children.length > 0;
        const expanded = hasChildren && isSectionNavExpanded(node.blockId);
        const shortLabel = limitNavWords(node.label, 10);
        const caret = hasChildren
            ? `
                <button
                    type="button"
                    class="splis-ob-section-nav-caret"
                    data-nav-toggle="${node.blockId}"
                    aria-expanded="${expanded ? 'true' : 'false'}"
                    aria-label="${expanded ? 'Collapse' : 'Expand'} ${escapeHtml(node.label)}"
                >${sectionNavCaretHtml()}</button>
            `
            : '<span class="splis-ob-section-nav-caret-spacer" aria-hidden="true"></span>';

        const children = hasChildren
            ? `
                <ul class="splis-ob-section-nav-children${expanded ? '' : ' is-collapsed'}">
                    ${node.children.map((child) => renderSectionNavNode(child)).join('')}
                </ul>
            `
            : '';

        const kindClass = node.kind ? ` splis-ob-section-nav-item--${node.kind}` : '';

        return `
            <li class="splis-ob-section-nav-item splis-ob-section-nav-item--level-${node.level}${kindClass}${hasChildren ? ' has-children' : ''}${expanded ? ' is-expanded' : ''}">
                <div class="splis-ob-section-nav-row">
                    ${caret}
                    <a
                        href="#ob-block-${node.blockId}"
                        class="splis-ob-section-nav-link"
                        data-block-id="${node.blockId}"
                        title="${escapeHtml(node.label)}"
                    >${escapeHtml(shortLabel)}</a>
                </div>
                ${children}
            </li>
        `;
    }

    function renderSectionNav() {
        if (!sectionNavEl || !sectionNavList) {
            return;
        }

        const entries = sectionNavEntries();

        if (entries.length === 0) {
            sectionNavEl.classList.add('hidden');
            sectionNavList.innerHTML = '';
            teardownSectionNavObserver();
            return;
        }

        const tree = buildSectionNavTree(entries);
        const activeId = entries.some((entry) => entry.blockId === selectedBlockId)
            ? selectedBlockId
            : entries[0].blockId;
        ensureSectionNavAncestorsExpanded(tree, activeId);

        sectionNavEl.classList.remove('hidden');
        sectionNavList.innerHTML = tree.map((node) => renderSectionNavNode(node)).join('');

        setupSectionNavObserver(entries, tree);
        setActiveSectionNavLink(activeId);
        syncSectionNavExpandToggle();
    }

    function renderBlocks() {
        if (!blocksList || !blocksEmpty) {
            return;
        }

        if (blocks.length === 0) {
            blocksList.innerHTML = '';
            blocksEmpty.classList.remove('hidden');
            renderSectionNav();
            return;
        }

        blocksEmpty.classList.add('hidden');
        blocksList.innerHTML = blocks
            .map((block, index) => {
                const selected = block.id === selectedBlockId ? ' is-selected' : '';
                return `
                    <article id="ob-block-${block.id}" class="splis-ob-block${selected}" data-block-id="${block.id}">
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

        renderSectionNav();
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

        const richTitle = blockEl.querySelector('[data-rich-title]');
        if (richTitle) {
            content.title_html = sanitizeRichTitleHtml(richTitle.innerHTML);
            content.title = richTitlePlainText(content.title_html);
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

    function confirmAction({ title, message, confirmLabel = 'Delete', danger = true }) {
        if (!confirmDialog || !confirmTitleEl || !confirmMessageEl || !confirmOkBtn) {
            return Promise.resolve(window.confirm(message));
        }

        return new Promise((resolve) => {
            confirmResolve = resolve;
            confirmTitleEl.textContent = title;
            confirmMessageEl.textContent = message;
            confirmOkBtn.textContent = confirmLabel;
            confirmOkBtn.classList.toggle('splis-btn-danger', danger);
            confirmOkBtn.classList.toggle('splis-btn-primary', !danger);
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

    async function moveBlockToSection(blockId, section) {
        const currentBlock = blocks.find((item) => item.id === blockId);
        if (!currentBlock) {
            return;
        }

        const agendaItemId = currentBlock.agenda_item_id
            ?? (Array.isArray(currentBlock.content?.agenda_item_ids) && currentBlock.content.agenda_item_ids.length === 1
                ? Number(currentBlock.content.agenda_item_ids[0])
                : null);

        if (selectedBlockId !== null) {
            syncBlockFromDom(selectedBlockId);
        }

        try {
            setStatus('Moving to section…');
            const data = await api(blockUrl(urls.moveSection, blockId), {
                method: 'POST',
                body: JSON.stringify({ section }),
            });
            blocks = normalizeBlocks(data.blocks ?? []).sort((a, b) => a.sort_order - b.sort_order);
            documentState = data.document ?? documentState;

            if (agendaItemId) {
                const movedBlock = blocks.find(
                    (item) => item.agenda_item_id === agendaItemId
                        || (Array.isArray(item.content?.agenda_item_ids)
                            && item.content.agenda_item_ids.length === 1
                            && Number(item.content.agenda_item_ids[0]) === agendaItemId),
                );
                selectedBlockId = movedBlock?.id ?? blocks[0]?.id ?? null;
                loadAgendaPool(1, false);
            } else {
                selectedBlockId = blocks[0]?.id ?? null;
            }

            renderBlocks();
            setStatus('Moved to section');
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

    async function syncAgendas() {
        if (!canEdit || !urls.syncAgendas) {
            return;
        }

        const confirmed = await confirmAction({
            title: 'Auto-place Agendas?',
            message:
                'Place eligible agendas into this Order of Business using lifecycle rules (unassigned, unfinished, or committee reports). Manually moved items are left as-is. Items already in the correct section are skipped.',
            confirmLabel: 'Auto-place',
            danger: false,
        });

        if (!confirmed) {
            return;
        }

        try {
            setStatus('Auto-placing agendas…');
            const data = await api(urls.syncAgendas, { method: 'POST', body: '{}' });
            blocks = normalizeBlocks(data.blocks ?? blocks).sort((a, b) => a.sort_order - b.sort_order);
            documentState = data.document ?? documentState;
            if (selectedBlockId && !blocks.some((block) => block.id === selectedBlockId)) {
                selectedBlockId = blocks[0]?.id ?? null;
            }
            renderBlocks();
            loadAgendaPool(1, false);

            const added = Number(data.added ?? 0);
            const relocated = Number(data.relocated ?? 0);
            if (added === 0 && relocated === 0) {
                setStatus('No eligible agendas to place or move.');
            } else {
                const parts = [];
                if (added > 0) {
                    parts.push(`${added} placed`);
                }
                if (relocated > 0) {
                    parts.push(`${relocated} moved`);
                }
                setStatus(`Auto-place complete: ${parts.join(', ')}.`);
            }
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

    root.addEventListener('mousedown', (event) => {
        if (event.target.closest('[data-ob-rich-command]')) {
            event.preventDefault();
        }
    });

    root.addEventListener('click', (event) => {
        const target = event.target;

        const richCommand = target.closest('[data-ob-rich-command]');
        if (richCommand) {
            const editor = richCommand.closest('.splis-ob-rich-title-wrap')?.querySelector('[data-rich-title]');
            if (!editor || editor.getAttribute('contenteditable') !== 'true') {
                return;
            }

            editor.focus();

            if (richCommand.dataset.obRichCommand === 'hiliteColor') {
                if (selectionHasHighlight(editor)) {
                    removeHighlightFromSelection(editor);
                } else {
                    document.execCommand('styleWithCSS', false, true);
                    document.execCommand('hiliteColor', false, richCommand.dataset.obRichValue ?? '#fef08a');
                }
            } else {
                document.execCommand(
                    richCommand.dataset.obRichCommand,
                    false,
                    richCommand.dataset.obRichValue ?? null,
                );
            }

            editor.dispatchEvent(new Event('input', { bubbles: true }));
            return;
        }

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

        if (target.matches('#ob-sync-agendas')) {
            syncAgendas();
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

    root.addEventListener('paste', (event) => {
        const editor = event.target.closest('[data-rich-title]');
        if (!editor) {
            return;
        }

        event.preventDefault();
        document.execCommand('insertText', false, event.clipboardData?.getData('text/plain') ?? '');
    });

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

        if (target.matches('.splis-ob-move-section')) {
            const section = target.value;
            const blockId = Number(target.dataset.blockId);
            if (!section || !blockId) {
                return;
            }
            moveBlockToSection(blockId, section);
            target.value = '';
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

    if (sectionNavEl) {
        sectionNavEl.addEventListener('click', (event) => {
            const toggle = event.target.closest('[data-nav-toggle]');
            if (toggle) {
                event.preventDefault();
                event.stopPropagation();
                const blockId = Number(toggle.dataset.navToggle);
                if (!Number.isFinite(blockId)) {
                    return;
                }

                if (sectionNavExpandedIds === null) {
                    sectionNavExpandedIds = new Set(
                        sectionNavParentIds(buildSectionNavTree(sectionNavEntries())).map(Number),
                    );
                }

                if (sectionNavExpandedIds.has(blockId)) {
                    sectionNavExpandedIds.delete(blockId);
                } else {
                    sectionNavExpandedIds.add(blockId);
                }

                renderSectionNav();
                return;
            }

            const link = event.target.closest('.splis-ob-section-nav-link');
            if (!link) {
                return;
            }

            event.preventDefault();
            const blockId = Number(link.dataset.blockId);
            if (!Number.isFinite(blockId)) {
                return;
            }

            const tree = buildSectionNavTree(sectionNavEntries());
            if (sectionNavExpandedIds === null) {
                // keep default expanded
            } else {
                ensureSectionNavAncestorsExpanded(tree, blockId);
            }

            selectedBlockId = blockId;
            renderBlocks();
            requestAnimationFrame(() => {
                scrollToObBlock(blockId);
                setActiveSectionNavLink(blockId);
            });
        });
    }

    function openSectionNavPanel() {
        if (!sectionNavEl) {
            return;
        }
        sectionNavEl.classList.add('is-open');
        sectionNavEl.classList.remove('is-closed');
        sectionNavToggle?.setAttribute('aria-expanded', 'true');
    }

    function closeSectionNavPanel() {
        if (!sectionNavEl) {
            return;
        }
        sectionNavEl.classList.remove('is-open');
        sectionNavEl.classList.add('is-closed');
        sectionNavToggle?.setAttribute('aria-expanded', 'false');
    }

    sectionNavExpandToggleBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        applySectionNavExpandedState(!sectionNavParentsAreExpanded());
    });

    sectionNavCloseBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        closeSectionNavPanel();
    });

    sectionNavToggle?.addEventListener('click', () => {
        openSectionNavPanel();
    });

    function readSectionNavLayout() {
        try {
            const raw = localStorage.getItem(SECTION_NAV_LAYOUT_KEY);
            if (!raw) {
                return null;
            }
            const saved = JSON.parse(raw);
            if (!saved || typeof saved !== 'object') {
                return null;
            }
            return saved;
        } catch {
            return null;
        }
    }

    function persistSectionNavLayout(partial) {
        try {
            const current = readSectionNavLayout() ?? {};
            localStorage.setItem(SECTION_NAV_LAYOUT_KEY, JSON.stringify({ ...current, ...partial }));
        } catch {
            // ignore quota / private mode
        }
    }

    function clampSectionNavPosition(left, top, width, height) {
        const margin = 8;
        const w = width ?? sectionNavPanel?.offsetWidth ?? sectionNavEl?.offsetWidth ?? 280;
        const h = height ?? sectionNavPanel?.offsetHeight ?? sectionNavEl?.offsetHeight ?? 320;
        const maxLeft = Math.max(margin, window.innerWidth - w - margin);
        const maxTop = Math.max(margin, window.innerHeight - h - margin);

        return {
            left: Math.min(Math.max(margin, left), maxLeft),
            top: Math.min(Math.max(margin, top), maxTop),
        };
    }

    function clampSectionNavSize(width, height) {
        const minW = 240;
        const minH = 220;
        const maxW = Math.max(minW, window.innerWidth - 16);
        const maxH = Math.max(minH, window.innerHeight - 16);

        return {
            width: Math.min(Math.max(minW, width), maxW),
            height: Math.min(Math.max(minH, height), maxH),
        };
    }

    function applySectionNavPosition(left, top, persist = true) {
        if (!sectionNavEl) {
            return;
        }

        const size = {
            width: sectionNavPanel?.offsetWidth || Number.parseFloat(sectionNavPanel?.style.width) || 280,
            height: sectionNavPanel?.offsetHeight || Number.parseFloat(sectionNavPanel?.style.height) || 360,
        };
        const clamped = clampSectionNavPosition(left, top, size.width, size.height);
        sectionNavEl.style.left = `${clamped.left}px`;
        sectionNavEl.style.top = `${clamped.top}px`;
        sectionNavEl.style.right = 'auto';
        sectionNavEl.style.bottom = 'auto';
        sectionNavEl.style.transform = 'none';
        sectionNavEl.classList.add('is-repositioned');

        if (persist) {
            persistSectionNavLayout(clamped);
        }
    }

    function applySectionNavSize(width, height, persist = true) {
        if (!sectionNavPanel) {
            return;
        }

        const clamped = clampSectionNavSize(width, height);
        sectionNavPanel.style.width = `${clamped.width}px`;
        sectionNavPanel.style.height = `${clamped.height}px`;
        sectionNavPanel.style.maxHeight = 'none';
        sectionNavEl?.classList.add('is-resized');

        if (sectionNavEl?.classList.contains('is-repositioned')) {
            const left = Number.parseFloat(sectionNavEl.style.left);
            const top = Number.parseFloat(sectionNavEl.style.top);
            if (Number.isFinite(left) && Number.isFinite(top)) {
                applySectionNavPosition(left, top, false);
            }
        }

        if (persist) {
            persistSectionNavLayout(clamped);
        }
    }

    function restoreSectionNavLayout() {
        if (!sectionNavEl) {
            return;
        }

        const saved = readSectionNavLayout();
        if (!saved) {
            return;
        }

        if (typeof saved.width === 'number' && typeof saved.height === 'number') {
            applySectionNavSize(saved.width, saved.height, false);
        }
        if (typeof saved.left === 'number' && typeof saved.top === 'number') {
            applySectionNavPosition(saved.left, saved.top, false);
        }
    }

    function bindSectionNavDrag() {
        if (!sectionNavEl || !sectionNavDragHandle) {
            return;
        }

        const onPointerMove = (event) => {
            if (!sectionNavDragState) {
                return;
            }

            const left = event.clientX - sectionNavDragState.offsetX;
            const top = event.clientY - sectionNavDragState.offsetY;
            applySectionNavPosition(left, top, false);
        };

        const onPointerUp = (event) => {
            if (!sectionNavDragState) {
                return;
            }

            sectionNavEl.classList.remove('is-dragging');
            sectionNavDragHandle.releasePointerCapture?.(sectionNavDragState.pointerId);
            const left = event.clientX - sectionNavDragState.offsetX;
            const top = event.clientY - sectionNavDragState.offsetY;
            applySectionNavPosition(left, top, true);
            sectionNavDragState = null;
            window.removeEventListener('pointermove', onPointerMove);
            window.removeEventListener('pointerup', onPointerUp);
        };

        sectionNavDragHandle.addEventListener('pointerdown', (event) => {
            if (event.button !== 0) {
                return;
            }
            if (event.target.closest('button, .splis-ob-section-nav-tool')) {
                return;
            }

            event.preventDefault();
            const rect = sectionNavEl.getBoundingClientRect();
            sectionNavDragState = {
                pointerId: event.pointerId,
                offsetX: event.clientX - rect.left,
                offsetY: event.clientY - rect.top,
            };
            sectionNavEl.classList.add('is-dragging');
            sectionNavDragHandle.setPointerCapture?.(event.pointerId);
            applySectionNavPosition(rect.left, rect.top, false);
            window.addEventListener('pointermove', onPointerMove);
            window.addEventListener('pointerup', onPointerUp);
        });
    }

    function bindSectionNavResize() {
        if (!sectionNavEl || !sectionNavPanel || !sectionNavResizeHandle) {
            return;
        }

        const onPointerMove = (event) => {
            if (!sectionNavResizeState) {
                return;
            }

            const width = sectionNavResizeState.startWidth + (event.clientX - sectionNavResizeState.startX);
            const height = sectionNavResizeState.startHeight + (event.clientY - sectionNavResizeState.startY);
            applySectionNavSize(width, height, false);
        };

        const onPointerUp = (event) => {
            if (!sectionNavResizeState) {
                return;
            }

            sectionNavEl.classList.remove('is-resizing');
            sectionNavResizeHandle.releasePointerCapture?.(sectionNavResizeState.pointerId);
            const width = sectionNavResizeState.startWidth + (event.clientX - sectionNavResizeState.startX);
            const height = sectionNavResizeState.startHeight + (event.clientY - sectionNavResizeState.startY);
            applySectionNavSize(width, height, true);
            sectionNavResizeState = null;
            window.removeEventListener('pointermove', onPointerMove);
            window.removeEventListener('pointerup', onPointerUp);
        };

        sectionNavResizeHandle.addEventListener('pointerdown', (event) => {
            if (event.button !== 0) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            const rect = sectionNavPanel.getBoundingClientRect();
            sectionNavResizeState = {
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                startWidth: rect.width,
                startHeight: rect.height,
            };
            sectionNavEl.classList.add('is-resizing');
            sectionNavResizeHandle.setPointerCapture?.(event.pointerId);
            window.addEventListener('pointermove', onPointerMove);
            window.addEventListener('pointerup', onPointerUp);
        });
    }

    window.addEventListener('resize', () => {
        if (!sectionNavEl) {
            return;
        }

        if (sectionNavEl.classList.contains('is-resized') && sectionNavPanel) {
            const width = Number.parseFloat(sectionNavPanel.style.width);
            const height = Number.parseFloat(sectionNavPanel.style.height);
            if (Number.isFinite(width) && Number.isFinite(height)) {
                applySectionNavSize(width, height, true);
            }
        }

        if (sectionNavEl.classList.contains('is-repositioned')) {
            const left = Number.parseFloat(sectionNavEl.style.left);
            const top = Number.parseFloat(sectionNavEl.style.top);
            if (Number.isFinite(left) && Number.isFinite(top)) {
                applySectionNavPosition(left, top, true);
            }
        }
    });

    bindSectionNavDrag();
    bindSectionNavResize();
    restoreSectionNavLayout();

    renderAddBlockButtons();
    renderBlocks();
    if (canEdit) {
        loadAgendaPool(1, false);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initObMaker();
});
