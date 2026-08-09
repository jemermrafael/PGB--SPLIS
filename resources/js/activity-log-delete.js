import { initConfirmDialog, showConfirmDialog } from './confirm-dialog';

function csrfToken(form) {
    return form?.querySelector('input[name="_token"]')?.value
        ?? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        ?? '';
}

function updateHistoryChrome(timeline, removedAction) {
    const remaining = timeline.querySelectorAll('.splis-activity-timeline-item').length;
    const root = timeline.closest('details.splis-accordion, aside.splis-card, div.splis-card');

    if (!root) {
        return;
    }

    const countEl = root.querySelector('.splis-accordion-count');
    if (countEl) {
        countEl.textContent = remaining.toLocaleString();
    }

    const subtitleEl = root.querySelector('.splis-card-subtitle');
    if (subtitleEl && removedAction === 'agenda.added_to_ob') {
        const match = subtitleEl.textContent.match(/Added to Order of Business\s+(\d+)/i);
        if (match) {
            const next = Math.max(0, Number.parseInt(match[1], 10) - 1);
            if (next > 0) {
                subtitleEl.textContent = `Added to Order of Business ${next} ${next === 1 ? 'time' : 'times'}`;
            } else {
                subtitleEl.remove();
            }
        }
    }

    if (remaining === 0) {
        root.remove();
    }
}

export function initActivityLogDelete() {
    initConfirmDialog();

    document.querySelectorAll('[data-activity-log-delete-trigger]').forEach((button) => {
        if (button.dataset.activityLogDeleteBound === '1') {
            return;
        }

        button.dataset.activityLogDeleteBound = '1';

        button.addEventListener('click', async () => {
            const form = button.closest('form[data-activity-log-delete-form]');

            if (!form || button.disabled) {
                return;
            }

            const confirmed = await showConfirmDialog({
                title: 'Remove from history?',
                message: 'This history entry will be permanently removed. This cannot be undone.',
                confirmLabel: 'Remove',
                danger: true,
            });

            if (!confirmed) {
                return;
            }

            button.disabled = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(form),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: new FormData(form),
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(data.message || 'Could not remove history entry.');
                }

                const item = form.closest('.splis-activity-timeline-item');
                const timeline = form.closest('.splis-activity-timeline');
                const action = data.action || form.dataset.activityLogAction || null;

                item?.remove();

                if (timeline) {
                    updateHistoryChrome(timeline, action);
                }
            } catch (error) {
                button.disabled = false;
                window.alert(error.message || 'Could not remove history entry.');
            }
        });
    });
}
