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

function debounce(fn, ms) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), ms);
    };
}

export function initCommitteeReportSummaryMaker() {
    const root = document.getElementById('scr-maker');
    if (!root) {
        return;
    }

    const form = root.querySelector('form[data-scr-maker-form]');
    const saveStatus = document.getElementById('scr-save-status');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        ?? form?.querySelector('input[name="_token"]')?.value
        ?? '';

    function setStatus(message, isError = false) {
        if (!saveStatus) {
            return;
        }
        saveStatus.textContent = message;
        saveStatus.classList.toggle('text-red-600', isError);
        saveStatus.classList.toggle('dark:text-red-400', isError);
        saveStatus.classList.toggle('text-slate-500', !isError);
    }

    function syncAllEditors() {
        root.querySelectorAll('[data-scr-rich-wrap]').forEach((wrap) => syncEditorToInputs(wrap));
    }

    const saveSummary = debounce(async () => {
        if (!form) {
            return;
        }

        syncAllEditors();
        setStatus('Saving…');

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

            setStatus('Saved');
        } catch (error) {
            setStatus(error.message || 'Save failed.', true);
        }
    }, 500);

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
        saveSummary();
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

    root.addEventListener('mousedown', (event) => {
        if (event.target.closest('[data-scr-rich-command]')) {
            event.preventDefault();
        }
    });

    root.addEventListener('click', (event) => {
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
            saveSummary();
            return;
        }

        if (event.target.closest('form[data-scr-maker-form]') && event.target.matches('input, textarea, select')) {
            saveSummary();
        }
    });

    root.addEventListener('change', (event) => {
        if (event.target.closest('form[data-scr-maker-form]') && event.target.matches('input, textarea, select')) {
            saveSummary();
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
        saveSummary();
    });

    root.addEventListener('keydown', (event) => {
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
        syncAllEditors();
        setStatus('Saving…');

        fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: new FormData(form),
        })
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const validationMessage = data.errors
                        ? Object.values(data.errors).flat().find(Boolean)
                        : null;
                    throw new Error(validationMessage ?? data.message ?? 'Save failed.');
                }
                setStatus('Saved');
            })
            .catch((error) => {
                setStatus(error.message || 'Save failed.', true);
            });
    });
}
