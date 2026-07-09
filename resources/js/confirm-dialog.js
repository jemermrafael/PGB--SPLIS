let confirmResolve = null;
let confirmDialogInitialized = false;

function getConfirmDialogElements() {
    return {
        dialog: document.getElementById('splis-confirm-dialog'),
        titleEl: document.getElementById('splis-confirm-title'),
        messageEl: document.getElementById('splis-confirm-message'),
        okBtn: document.getElementById('splis-confirm-ok'),
    };
}

function closeConfirmDialog(result) {
    const { dialog } = getConfirmDialogElements();

    if (!dialog) {
        return;
    }

    dialog.classList.remove('is-open');
    dialog.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('splis-ob-dialog-open');

    if (confirmResolve) {
        confirmResolve(result);
        confirmResolve = null;
    }
}

export function initConfirmDialog() {
    if (confirmDialogInitialized) {
        return;
    }

    const { dialog, okBtn } = getConfirmDialogElements();

    if (!dialog || !okBtn) {
        return;
    }

    dialog.querySelectorAll('[data-confirm-cancel]').forEach((element) => {
        element.addEventListener('click', () => closeConfirmDialog(false));
    });

    okBtn.addEventListener('click', () => closeConfirmDialog(true));

    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeConfirmDialog(false);
        }
    });

    confirmDialogInitialized = true;
}

export function showConfirmDialog({
    title = 'Confirm action',
    message = '',
    confirmLabel = 'Confirm',
    danger = true,
} = {}) {
    const { dialog, titleEl, messageEl, okBtn } = getConfirmDialogElements();

    if (!dialog || !titleEl || !messageEl || !okBtn) {
        return Promise.resolve(window.confirm(message || title));
    }

    initConfirmDialog();

    return new Promise((resolve) => {
        confirmResolve = resolve;
        titleEl.textContent = title;
        messageEl.textContent = message;
        okBtn.textContent = confirmLabel;
        okBtn.classList.toggle('splis-btn-danger', danger);
        okBtn.classList.toggle('splis-btn-primary', !danger);
        dialog.classList.add('is-open');
        dialog.setAttribute('aria-hidden', 'false');
        document.body.classList.add('splis-ob-dialog-open');
        okBtn.focus();
    });
}
