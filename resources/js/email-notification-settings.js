const SAMPLE_PLACEHOLDERS = {
    title: 'Committee referral update',
    body: 'Your referred agenda item needs attention before the deadline.',
    label: 'Agenda #342',
    committee: 'Committee on Finance',
    target: 'Resolution',
    session: '52nd Regular Session — July 27, 2026',
    document_title: 'Approving Supplemental Budget No. 1',
    due_date: 'August 5, 2026',
    days_left_suffix: ' (3 days left)',
    number_suffix: ' No. 2026-323',
    member_name: 'Hon. Ma. Cristina M. Garcia',
    report_title_suffix: ': Finance Committee Report',
    app_name: document.querySelector('meta[name="app-name"]')?.content || 'SPLIS',
};

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function looksLikeHtml(value) {
    return /<\s*\/?\s*[a-z][\s\S]*>/i.test(String(value ?? ''));
}

function displayHtml(value) {
    const text = String(value ?? '');
    if (text.trim() === '') {
        return '';
    }

    return looksLikeHtml(text) ? text : escapeHtml(text).replaceAll('\n', '<br>');
}

function applyPlaceholders(template) {
    return String(template ?? '').replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, (_, key) => {
        const sample = SAMPLE_PLACEHOLDERS[key.toLowerCase()];
        return sample !== undefined ? sample : `{{${key}}}`;
    });
}

function syncEditorToInput(wrap) {
    const editor = wrap.querySelector('[data-email-rich-editor]');
    const input = wrap.querySelector('[data-email-rich-input]');
    if (!editor || !input) {
        return;
    }

    input.value = editor.innerHTML.trim() === '<br>' ? '' : editor.innerHTML;
}

function initRichEditors(root) {
    root.querySelectorAll('[data-email-rich-wrap]').forEach((wrap) => {
        const editor = wrap.querySelector('[data-email-rich-editor]');
        const input = wrap.querySelector('[data-email-rich-input]');
        if (!editor || !input) {
            return;
        }

        editor.innerHTML = displayHtml(input.value);
    });

    root.addEventListener('mousedown', (event) => {
        if (event.target.closest('[data-email-rich-command]')) {
            event.preventDefault();
        }
    });

    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-email-rich-command]');
        if (!button) {
            return;
        }

        const wrap = button.closest('[data-email-rich-wrap]');
        const editor = wrap?.querySelector('[data-email-rich-editor]');
        if (!wrap || !editor) {
            return;
        }

        editor.focus();
        const command = button.dataset.emailRichCommand;

        if (command === 'createLink') {
            const url = window.prompt('Link URL', 'https://');
            if (url && url.trim() !== '' && url.trim() !== 'https://') {
                document.execCommand('createLink', false, url.trim());
            }
        } else {
            document.execCommand(command, false, null);
        }

        syncEditorToInput(wrap);
    });

    root.addEventListener('input', (event) => {
        const editor = event.target.closest('[data-email-rich-editor]');
        if (!editor) {
            return;
        }

        const wrap = editor.closest('[data-email-rich-wrap]');
        if (wrap) {
            syncEditorToInput(wrap);
        }
    });

    root.addEventListener('paste', (event) => {
        const editor = event.target.closest('[data-email-rich-editor]');
        if (!editor) {
            return;
        }

        const html = event.clipboardData?.getData('text/html');
        if (html) {
            return;
        }

        event.preventDefault();
        document.execCommand('insertText', false, event.clipboardData?.getData('text/plain') ?? '');
    });

    root.querySelector('form')?.addEventListener('submit', () => {
        root.querySelectorAll('[data-email-rich-wrap]').forEach(syncEditorToInput);
    });
}

function initPreviewModal(root) {
    const modal = document.getElementById('email-template-preview-modal');
    if (!modal) {
        return;
    }

    const titleEl = document.getElementById('email-template-preview-title');
    const subjectEl = document.getElementById('email-template-preview-subject');
    const headingEl = document.getElementById('email-template-preview-heading');
    const bodyEl = document.getElementById('email-template-preview-body');
    const actionWrap = document.getElementById('email-template-preview-action-wrap');
    const actionEl = document.getElementById('email-template-preview-action');

    function openModal() {
        modal.hidden = false;
        document.body.classList.add('splis-modal-open');
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('splis-modal-open');
    }

    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-email-preview]');
        if (!button) {
            return;
        }

        const wrap = button.closest('[data-email-rich-wrap]');
        if (wrap) {
            syncEditorToInput(wrap);
        }

        const subjectInput = document.querySelector(button.dataset.previewSubject);
        const bodyInput = document.querySelector(button.dataset.previewBody);
        const actionInput = document.querySelector(button.dataset.previewAction);

        const subject = applyPlaceholders(subjectInput?.value || '(No subject)');
        const body = applyPlaceholders(bodyInput?.value || '');
        const action = applyPlaceholders(actionInput?.value || '').trim();

        if (titleEl) {
            titleEl.textContent = `Email preview — ${button.dataset.previewTitle || 'Template'}`;
        }
        if (subjectEl) {
            subjectEl.textContent = subject;
        }
        if (headingEl) {
            headingEl.textContent = subject;
        }
        if (bodyEl) {
            bodyEl.innerHTML = displayHtml(body) || '<p class="text-slate-400">Empty body</p>';
        }
        if (actionWrap && actionEl) {
            const hasAction = action !== '';
            actionWrap.classList.toggle('hidden', !hasAction);
            actionEl.textContent = action || 'View details';
        }

        openModal();
    });

    modal.querySelectorAll('[data-email-preview-close]').forEach((el) => {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
}

export function initEmailNotificationSettings() {
    const root = document.getElementById('email-notification-settings');
    if (!root) {
        return;
    }

    const tabs = [...root.querySelectorAll('[data-email-tab]')];
    const panels = [...root.querySelectorAll('[data-email-panel]')];
    const activeInput = document.getElementById('email-settings-active-tab');
    const testTabInput = root.querySelector('[data-email-test-tab]');

    function activate(tabName) {
        tabs.forEach((tab) => {
            const active = tab.dataset.emailTab === tabName;
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.classList.toggle('ring-2', active);
            tab.classList.toggle('ring-brand-200', active);
        });

        panels.forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.emailPanel !== tabName);
        });

        if (activeInput) {
            activeInput.value = tabName;
        }
        if (testTabInput) {
            testTabInput.value = tabName;
        }

        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabName);
        window.history.replaceState({}, '', url);
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => activate(tab.dataset.emailTab));
    });

    root.querySelector('[data-smtp-preset="gmail"]')?.addEventListener('click', () => {
        const setValue = (id, value) => {
            const el = document.getElementById(id);
            if (el) {
                el.value = value;
            }
        };

        setValue('smtp_mailer', 'smtp');
        setValue('smtp_encryption', 'tls');
        setValue('smtp_host', 'smtp.gmail.com');
        setValue('smtp_port', '587');

        const username = document.getElementById('smtp_username');
        const fromAddress = document.getElementById('smtp_from_address');
        if (username && !username.value.trim()) {
            username.focus();
        }
        if (fromAddress && username?.value.trim() && !fromAddress.value.trim()) {
            fromAddress.value = username.value.trim();
        }

        activate('smtp');
    });

    initRichEditors(root);
    initPreviewModal(root);
}
