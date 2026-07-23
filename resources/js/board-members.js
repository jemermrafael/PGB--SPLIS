import { initConfirmDialog, showConfirmDialog } from './confirm-dialog';

function confirmOptionsFrom(element) {
    return {
        title: element.dataset.confirmTitle || 'Confirm action',
        message: element.dataset.confirmMessage || '',
        confirmLabel: element.dataset.confirmLabel || 'Confirm',
        danger: element.dataset.confirmDanger !== '0',
    };
}

export function initConfirmSubmitForms() {
    initConfirmDialog();

    document.querySelectorAll('form[data-confirm-submit]').forEach((form) => {
        if (form.dataset.confirmSubmitBound === '1') {
            return;
        }

        form.dataset.confirmSubmitBound = '1';

        form.addEventListener('submit', async (event) => {
            if (form.dataset.confirmAccepted === '1') {
                delete form.dataset.confirmAccepted;

                return;
            }

            event.preventDefault();

            const confirmed = await showConfirmDialog(confirmOptionsFrom(form));

            if (! confirmed) {
                return;
            }

            form.dataset.confirmAccepted = '1';
            form.requestSubmit();
        });
    });

    document.querySelectorAll('button[data-confirm-submit], input[type="submit"][data-confirm-submit]').forEach((button) => {
        if (!(button instanceof HTMLElement) || button.dataset.confirmSubmitBound === '1') {
            return;
        }

        const form = button instanceof HTMLButtonElement || button instanceof HTMLInputElement
            ? button.form
            : null;

        if (! form) {
            return;
        }

        // Forms already bound above handle their own submit confirm.
        if (form.hasAttribute('data-confirm-submit')) {
            return;
        }

        button.dataset.confirmSubmitBound = '1';

        button.addEventListener('click', async (event) => {
            if (form.dataset.confirmAccepted === '1') {
                delete form.dataset.confirmAccepted;

                return;
            }

            event.preventDefault();

            const confirmed = await showConfirmDialog(confirmOptionsFrom(button));

            if (! confirmed) {
                return;
            }

            form.dataset.confirmAccepted = '1';
            form.requestSubmit(button);
        });
    });
}

export function initBoardMemberBulkDelete() {
    const root = document.getElementById('board-members-index');

    if (! root) {
        return;
    }

    initConfirmDialog();

    const selectAll = root.querySelector('[data-board-member-select-all]');
    const checkboxes = () => Array.from(root.querySelectorAll('[data-board-member-checkbox]'));
    const bulkForm = root.querySelector('[data-board-member-bulk-form]');
    const bulkButton = root.querySelector('[data-board-member-bulk-delete]');
    const selectedCount = root.querySelector('[data-board-member-selected-count]');

    function syncSelectionUi() {
        const boxes = checkboxes();
        const selected = boxes.filter((box) => box.checked);
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

        if (bulkButton) {
            bulkButton.disabled = selected.length === 0;
        }
    }

    selectAll?.addEventListener('change', () => {
        checkboxes().forEach((box) => {
            box.checked = selectAll.checked;
        });
        syncSelectionUi();
    });

    root.addEventListener('change', (event) => {
        if (event.target?.matches?.('[data-board-member-checkbox]')) {
            syncSelectionUi();
        }
    });

    bulkForm?.addEventListener('submit', async (event) => {
        if (bulkForm.dataset.confirmAccepted === '1') {
            delete bulkForm.dataset.confirmAccepted;

            return;
        }

        event.preventDefault();

        const selected = checkboxes().filter((box) => box.checked);

        if (selected.length === 0) {
            return;
        }

        // Rebuild hidden ids so only checked rows are posted.
        bulkForm.querySelectorAll('input[name="ids[]"]').forEach((input) => input.remove());
        selected.forEach((box) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = box.value;
            bulkForm.appendChild(input);
        });

        const confirmed = await showConfirmDialog({
            title: selected.length === 1 ? 'Delete Board Member?' : `Delete ${selected.length} Board Members?`,
            message: 'Selected Members will be removed from the roster, including Committee Assignments. This cannot be undone.',
            confirmLabel: selected.length === 1 ? 'Delete' : `Delete ${selected.length}`,
            danger: true,
        });

        if (! confirmed) {
            return;
        }

        bulkForm.dataset.confirmAccepted = '1';
        bulkForm.requestSubmit();
    });

    syncSelectionUi();
}
