/**
 * Toggle edit/delete actions on list pages.
 * Root: [data-list-edit]
 * Toggle: [data-list-edit-toggle] with optional [data-list-edit-label]
 * Hidden until editing: [data-list-edit-only]
 * Cleared on exit: [data-list-edit-checkbox]
 */
export function initListEditMode() {
    document.querySelectorAll('[data-list-edit]').forEach((root) => {
        const toggle = root.querySelector('[data-list-edit-toggle]');
        const label = root.querySelector('[data-list-edit-label]');

        if (! toggle) {
            return;
        }

        function setEditing(editing) {
            root.dataset.editing = editing ? '1' : '0';
            toggle.setAttribute('aria-pressed', editing ? 'true' : 'false');

            if (label) {
                label.textContent = editing
                    ? (toggle.dataset.doneLabel || 'Done')
                    : (toggle.dataset.editLabel || 'Edit List');
            }

            if (! editing) {
                root.querySelectorAll('[data-list-edit-checkbox]').forEach((box) => {
                    box.checked = false;
                });
                root.querySelectorAll('[data-list-edit-select-all]').forEach((box) => {
                    box.checked = false;
                    box.indeterminate = false;
                });
            }

            root.dispatchEvent(new CustomEvent('list-edit:change', {
                bubbles: true,
                detail: { editing },
            }));
        }

        toggle.addEventListener('click', () => {
            setEditing(root.dataset.editing !== '1');
        });

        setEditing(false);
    });
}
