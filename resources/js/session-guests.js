export function initSessionGuests() {
    const root = document.getElementById('session-guests');
    if (!root) {
        return;
    }

    const rowsEl = root.querySelector('[data-guest-rows]');
    const template = root.querySelector('[data-guest-template]');
    const addBtn = root.querySelector('[data-guest-add]');

    if (!rowsEl || !template || !addBtn) {
        return;
    }

    function nextIndex() {
        return rowsEl.querySelectorAll('[data-guest-row]').length;
    }

    function reindex() {
        rowsEl.querySelectorAll('[data-guest-row]').forEach((row, index) => {
            row.querySelectorAll('input[name]').forEach((input) => {
                input.name = input.name.replace(/guests\[\d+]/, `guests[${index}]`);
            });
        });
    }

    addBtn.addEventListener('click', () => {
        const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex()));
        rowsEl.insertAdjacentHTML('beforeend', html);
        const added = rowsEl.querySelector('[data-guest-row]:last-child input');
        added?.focus();
    });

    root.addEventListener('click', (event) => {
        const removeBtn = event.target.closest('[data-guest-remove]');
        if (!removeBtn || !root.contains(removeBtn)) {
            return;
        }

        const row = removeBtn.closest('[data-guest-row]');
        if (!row) {
            return;
        }

        if (rowsEl.querySelectorAll('[data-guest-row]').length === 1) {
            row.querySelectorAll('input').forEach((input) => {
                input.value = '';
            });
            return;
        }

        row.remove();
        reindex();
    });
}

export function initSessionAttendanceSelectAll() {
    const selectAll = document.getElementById('attendance-select-all');
    const roster = document.getElementById('attendance-roster');
    const countEl = document.querySelector('[data-attendance-selected-count]');

    if (!selectAll || !roster) {
        return;
    }

    const checkboxes = () => Array.from(roster.querySelectorAll('[data-attendance-checkbox]'));

    function syncSelectAllState() {
        const boxes = checkboxes();
        const checkedCount = boxes.filter((box) => box.checked).length;

        selectAll.checked = boxes.length > 0 && checkedCount === boxes.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;

        if (countEl) {
            countEl.textContent = boxes.length === 0
                ? ''
                : `${checkedCount} of ${boxes.length} present`;
        }
    }

    selectAll.addEventListener('change', () => {
        const checked = selectAll.checked;
        checkboxes().forEach((box) => {
            box.checked = checked;
        });
        syncSelectAllState();
    });

    roster.addEventListener('change', (event) => {
        if (event.target?.matches?.('[data-attendance-checkbox]')) {
            syncSelectAllState();
        }
    });

    syncSelectAllState();
}
