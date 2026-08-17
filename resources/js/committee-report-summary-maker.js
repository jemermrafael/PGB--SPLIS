import { showConfirmDialog } from './confirm-dialog';

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function styleHasUnderline(node) {
    const decoration = `${node.style?.textDecorationLine || ''} ${node.style?.textDecoration || ''}`.toLowerCase();

    return decoration.includes('underline');
}

function styleHasHighlight(node) {
    const background = `${node.style?.backgroundColor || ''} ${node.style?.background || ''}`.toLowerCase();

    return background !== ''
        && !background.includes('transparent')
        && background !== 'rgba(0, 0, 0, 0)';
}

function sanitizeRichHtml(html) {
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

        if (tag === 'u' || ((tag === 'span' || tag === 'font') && styleHasUnderline(node))) {
            return children ? `<u>${children}</u>` : '';
        }

        if (tag === 'mark' || ((tag === 'span' || tag === 'font') && styleHasHighlight(node))) {
            return children ? `<mark>${children}</mark>` : '';
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

function richPlainText(html) {
    const template = document.createElement('template');
    template.innerHTML = sanitizeRichHtml(html).replace(/<br>/g, '\n');

    return (template.content.textContent ?? '').replace(/\u00a0/g, ' ').trim();
}

function displayHtml(html, plain) {
    const formatted = sanitizeRichHtml(html ?? '');
    const plainText = String(plain ?? '').trim();

    if (formatted && richPlainText(formatted).replace(/\s+/g, ' ') === plainText.replace(/\s+/g, ' ')) {
        return formatted;
    }

    return escapeHtml(plainText).replace(/\r?\n/g, '<br>');
}

function elementIsHighlight(node) {
    if (!node || node.nodeType !== Node.ELEMENT_NODE) {
        return false;
    }

    if (node.nodeName === 'MARK') {
        return true;
    }

    return styleHasHighlight(node);
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
            el.style.background = '';
            if (el.getAttribute('style')?.trim() === '') {
                el.removeAttribute('style');
            }
        }
    });

    editor.normalize();
}

function applyHighlight(editor) {
    if (selectionHasHighlight(editor)) {
        removeHighlightFromSelection(editor);
        return;
    }

    document.execCommand('hiliteColor', false, '#fff200');
}

function syncEditorToInputs(wrap) {
    const editor = wrap.querySelector('[data-scr-rich-editor]');
    const plainInput = wrap.querySelector('[data-scr-rich-plain]');
    const htmlInput = wrap.querySelector('[data-scr-rich-html]');

    if (!editor || !plainInput || !htmlInput) {
        return;
    }

    const html = sanitizeRichHtml(editor.innerHTML);
    plainInput.value = richPlainText(html);
    htmlInput.value = html;
}

export function initCommitteeReportSummaryMaker() {
    const root = document.getElementById('scr-maker');
    if (!root) {
        return;
    }

    const form = root.querySelector('form[data-scr-maker-form]');
    const saveStatus = document.getElementById('scr-save-status');
    const saveBtn = document.getElementById('scr-save-document');
    const saveButtons = () => [
        saveBtn,
        ...root.querySelectorAll('[data-scr-bottom-save]'),
    ].filter(Boolean);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        ?? form?.querySelector('input[name="_token"]')?.value
        ?? '';

    let dirty = false;
    let isSaving = false;
    let suppressLeavePrompt = false;
    let unsavedHistoryTrap = false;
    let ignoringPopState = false;

    function armUnsavedHistoryTrap() {
        if (suppressLeavePrompt || unsavedHistoryTrap || !dirty) {
            return;
        }
        history.pushState({ splisScrUnsaved: 1 }, '', window.location.href);
        unsavedHistoryTrap = true;
    }

    function releaseUnsavedHistoryTrap() {
        if (!unsavedHistoryTrap) {
            return;
        }
        unsavedHistoryTrap = false;
        ignoringPopState = true;
        history.back();
        queueMicrotask(() => {
            ignoringPopState = false;
        });
    }

    function setStatus(message, isError = false) {
        if (!saveStatus) {
            return;
        }
        saveStatus.textContent = message;
        saveStatus.classList.toggle('text-red-600', isError);
        saveStatus.classList.toggle('dark:text-red-400', isError);
        saveStatus.classList.toggle('text-slate-500', !isError);
    }

    function updateSaveButtons() {
        const disabled = !dirty || isSaving;
        const html = isSaving
            ? 'Saving…'
            : '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3a9 9 0 1 1 0 18a9 9 0 0 1 0-18"/><path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"/></svg> Save';

        saveButtons().forEach((button) => {
            button.disabled = disabled;
            button.innerHTML = html;
            button.classList.add('inline-flex', 'items-center', 'gap-2');
        });
        root.classList.toggle('has-unsaved-changes', dirty);
    }

    function updateSaveUi(statusMessage = null) {
        updateSaveButtons();

        if (dirty) {
            armUnsavedHistoryTrap();
        } else {
            releaseUnsavedHistoryTrap();
        }

        if (statusMessage !== null) {
            setStatus(statusMessage);
        } else if (isSaving) {
            setStatus('Saving…');
        } else if (dirty) {
            setStatus('Unsaved changes');
        } else {
            setStatus('All changes saved');
        }
    }

    function markDirty() {
        if (dirty) {
            updateSaveUi();
            return;
        }
        dirty = true;
        updateSaveUi();
    }

    function clearDirty() {
        dirty = false;
        updateSaveUi();
    }

    function syncAllEditors() {
        root.querySelectorAll('[data-scr-rich-wrap]').forEach((wrap) => syncEditorToInputs(wrap));
    }

    function applyTemplate(wrap, html, mode = 'replace') {
        const editor = wrap.querySelector('[data-scr-rich-editor]');
        if (!editor || editor.getAttribute('contenteditable') !== 'true') {
            return;
        }

        const snippet = sanitizeRichHtml(html);
        if (!snippet) {
            return;
        }

        if (mode === 'append') {
            const current = sanitizeRichHtml(editor.innerHTML);
            editor.innerHTML = current
                ? `${current}<br><br>${snippet}`
                : snippet;
        } else {
            editor.innerHTML = snippet;
        }

        editor.focus();
        syncEditorToInputs(wrap);
        markDirty();
    }

    function runCommand(wrap, command) {
        const editor = wrap.querySelector('[data-scr-rich-editor]');
        if (!editor || editor.getAttribute('contenteditable') !== 'true') {
            return;
        }

        editor.focus();

        if (command === 'highlight') {
            applyHighlight(editor);
        } else {
            document.execCommand(command, false, null);
        }

        syncEditorToInputs(wrap);
        markDirty();
    }

    async function saveSummary() {
        if (!form || isSaving) {
            return false;
        }

        syncAllEditors();
        isSaving = true;
        updateSaveUi('Saving…');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: new FormData(form),
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                const validationMessage = data.errors
                    ? Object.values(data.errors).flat().find(Boolean)
                    : null;
                throw new Error(validationMessage ?? data.message ?? 'Save failed.');
            }

            dirty = false;
            isSaving = false;
            updateSaveUi('All changes saved');
            return true;
        } catch (error) {
            isSaving = false;
            updateSaveUi(error.message || 'Save failed.');
            setStatus(error.message || 'Save failed.', true);
            return false;
        }
    }

    async function confirmLeaveIfDirty(message = 'You have unsaved changes. Leave this page and discard them?') {
        if (!dirty) {
            return true;
        }

        return showConfirmDialog({
            title: 'Unsaved changes',
            message,
            confirmLabel: 'Leave',
            danger: true,
        });
    }

    root.querySelectorAll('[data-scr-rich-wrap]').forEach((wrap) => {
        const editor = wrap.querySelector('[data-scr-rich-editor]');
        const plainInput = wrap.querySelector('[data-scr-rich-plain]');
        const htmlInput = wrap.querySelector('[data-scr-rich-html]');
        if (!editor || !plainInput || !htmlInput) {
            return;
        }

        editor.innerHTML = displayHtml(htmlInput.value, plainInput.value);
        syncEditorToInputs(wrap);
    });

    updateSaveUi('All changes saved');

    function setRevisedTitleOpen(wrap, open, { focus = false } = {}) {
        const addRow = wrap.querySelector('[data-scr-revised-add-row]');
        const fields = wrap.querySelector('[data-scr-revised-fields]');
        const label = wrap.querySelector('[data-scr-revised-label]');
        const editorWrap = fields?.querySelector('[data-scr-rich-wrap]');
        const editor = editorWrap?.querySelector('[data-scr-rich-editor]');
        const plainInput = editorWrap?.querySelector('[data-scr-rich-plain]');
        const htmlInput = editorWrap?.querySelector('[data-scr-rich-html]');

        wrap.dataset.open = open ? '1' : '0';
        addRow?.classList.toggle('hidden', open);
        fields?.classList.toggle('hidden', !open);

        if (label) {
            label.disabled = !open;
            if (open && !label.value.trim()) {
                label.value = 'REVISED TITLE';
            }
        }

        if (!open) {
            if (label) {
                label.value = 'REVISED TITLE';
            }
            if (editor) {
                editor.innerHTML = '';
            }
            if (plainInput) {
                plainInput.value = '';
            }
            if (htmlInput) {
                htmlInput.value = '';
            }
            if (editor) {
                editor.setAttribute('contenteditable', 'false');
            }
        } else if (editor) {
            editor.setAttribute('contenteditable', 'true');
            if (focus) {
                editor.focus();
            }
        }
    }

    root.querySelectorAll('[data-scr-revised-wrap]').forEach((wrap) => {
        setRevisedTitleOpen(wrap, wrap.dataset.open === '1');
    });

    root.addEventListener('mousedown', (event) => {
        if (event.target.closest('[data-scr-rich-command]')) {
            event.preventDefault();
        }
    });

    root.addEventListener('click', (event) => {
        const revisedAdd = event.target.closest('[data-scr-revised-add]');
        if (revisedAdd) {
            const wrap = revisedAdd.closest('[data-scr-revised-wrap]');
            if (wrap) {
                setRevisedTitleOpen(wrap, true, { focus: true });
                markDirty();
            }
            return;
        }

        const revisedRemove = event.target.closest('[data-scr-revised-remove]');
        if (revisedRemove) {
            const wrap = revisedRemove.closest('[data-scr-revised-wrap]');
            if (wrap) {
                setRevisedTitleOpen(wrap, false);
                markDirty();
            }
            return;
        }

        const templateButton = event.target.closest('[data-scr-insert-template]');
        if (templateButton) {
            const wrap = templateButton.closest('[data-scr-rich-wrap]');
            if (!wrap) {
                return;
            }

            applyTemplate(
                wrap,
                templateButton.dataset.scrHtml ?? '',
                templateButton.dataset.scrMode || 'replace',
            );
            return;
        }

        const button = event.target.closest('[data-scr-rich-command]');
        if (!button) {
            return;
        }

        const wrap = button.closest('[data-scr-rich-wrap]');
        if (!wrap) {
            return;
        }

        runCommand(wrap, button.dataset.scrRichCommand);
    });

    root.addEventListener('input', (event) => {
        const editor = event.target.closest('[data-scr-rich-editor]');
        if (editor) {
            const wrap = editor.closest('[data-scr-rich-wrap]');
            if (wrap) {
                syncEditorToInputs(wrap);
            }
            markDirty();
            return;
        }

        if (event.target.closest('form[data-scr-maker-form]') && event.target.matches('input, textarea, select')) {
            markDirty();
        }
    });

    root.addEventListener('change', (event) => {
        if (event.target.closest('form[data-scr-maker-form]') && event.target.matches('input, textarea, select')) {
            markDirty();
        }
    });

    root.addEventListener('paste', (event) => {
        const editor = event.target.closest('[data-scr-rich-editor]');
        if (!editor) {
            return;
        }

        event.preventDefault();
        document.execCommand('insertText', false, event.clipboardData?.getData('text/plain') ?? '');
        const wrap = editor.closest('[data-scr-rich-wrap]');
        if (wrap) {
            syncEditorToInputs(wrap);
        }
        markDirty();
    });

    root.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            saveSummary();
            return;
        }

        const editor = event.target.closest('[data-scr-rich-editor]');
        if (!editor || editor.getAttribute('contenteditable') !== 'true') {
            return;
        }

        if (!(event.ctrlKey || event.metaKey)) {
            return;
        }

        const wrap = editor.closest('[data-scr-rich-wrap]');
        if (!wrap) {
            return;
        }

        const key = event.key.toLowerCase();

        if (key === 'b') {
            event.preventDefault();
            runCommand(wrap, 'bold');
            return;
        }

        if (key === 'u') {
            event.preventDefault();
            runCommand(wrap, 'underline');
            return;
        }

        if (key === 'h') {
            event.preventDefault();
            runCommand(wrap, 'highlight');
        }
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        saveSummary();
    });

    root.querySelector('[data-scr-sync-form]')?.addEventListener('submit', async (event) => {
        if (!dirty) {
            return;
        }

        event.preventDefault();

        const confirmed = await showConfirmDialog({
            title: 'Refresh from Order of Business?',
            message: 'You have unsaved changes. Refresh from OB will reload the page and discard them. Continue?',
            confirmLabel: 'Refresh',
            danger: true,
        });

        if (!confirmed) {
            return;
        }

        suppressLeavePrompt = true;
        event.target.submit();
    });

    window.addEventListener('beforeunload', (event) => {
        if (suppressLeavePrompt || !dirty) {
            return;
        }
        event.preventDefault();
        event.returnValue = '';
    });

    window.addEventListener('popstate', async () => {
        if (ignoringPopState) {
            return;
        }

        if (suppressLeavePrompt || !dirty) {
            unsavedHistoryTrap = false;
            return;
        }

        history.pushState({ splisScrUnsaved: 1 }, '', window.location.href);
        unsavedHistoryTrap = true;

        const leave = await confirmLeaveIfDirty(
            'You have unsaved changes. Leave this page and discard them?',
        );

        if (!leave) {
            return;
        }

        suppressLeavePrompt = true;
        unsavedHistoryTrap = false;
        ignoringPopState = true;
        history.go(-2);
    });

    window.addEventListener('keydown', async (event) => {
        const key = event.key;
        const isRefreshKey = key === 'F5'
            || ((event.ctrlKey || event.metaKey) && key.toLowerCase() === 'r');

        if (!isRefreshKey || suppressLeavePrompt || !dirty) {
            return;
        }

        event.preventDefault();

        const leave = await confirmLeaveIfDirty(
            'You have unsaved changes. Refresh this page and discard them?',
        );

        if (!leave) {
            return;
        }

        suppressLeavePrompt = true;
        window.location.reload();
    });

    document.addEventListener('click', async (event) => {
        if (suppressLeavePrompt || !dirty) {
            return;
        }

        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0) {
            return;
        }
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        if (link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }
        if (link.hasAttribute('data-pdf-modal-open')) {
            return;
        }

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
            return;
        }

        let url;
        try {
            url = new URL(link.href, window.location.href);
        } catch {
            return;
        }

        if (url.href === window.location.href) {
            return;
        }

        event.preventDefault();

        const leave = await confirmLeaveIfDirty(
            link.hasAttribute('data-scr-back')
                ? 'You have unsaved changes. Go back to the session and discard them?'
                : 'You have unsaved changes. Leave this page and discard them?',
        );

        if (!leave) {
            return;
        }

        suppressLeavePrompt = true;
        window.location.href = url.href;
    });
}
