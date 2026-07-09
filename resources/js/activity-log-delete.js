import { initConfirmDialog, showConfirmDialog } from './confirm-dialog';

export function initActivityLogDelete() {
    initConfirmDialog();

    document.querySelectorAll('[data-activity-log-delete-trigger]').forEach((button) => {
        if (button.dataset.activityLogDeleteBound === '1') {
            return;
        }

        button.dataset.activityLogDeleteBound = '1';

        button.addEventListener('click', async () => {
            const form = button.closest('form[data-activity-log-delete-form]');

            if (!form) {
                return;
            }

            const confirmed = await showConfirmDialog({
                title: 'Remove from history?',
                message: 'This history entry will be permanently removed. This cannot be undone.',
                confirmLabel: 'Remove',
                danger: true,
            });

            if (confirmed) {
                form.submit();
            }
        });
    });
}
