const EMPTY = '—';

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function formatCellValue(value, field) {
    if (value === null || value === undefined || value === '') {
        return `<span class="splis-version-compare-empty">${EMPTY}</span>`;
    }

    if (field.endsWith('_url')) {
        const url = escapeHtml(value);
        return `<a href="${url}" target="_blank" rel="noopener" class="splis-link break-all">${url}</a>`;
    }

    return escapeHtml(value);
}

function compareRows(leftSnapshot, rightSnapshot, fieldLabels) {
    return Object.entries(fieldLabels).map(([field, label]) => {
        const leftRaw = leftSnapshot[field] ?? null;
        const rightRaw = rightSnapshot[field] ?? null;
        const left = leftRaw === null || leftRaw === '' ? null : String(leftRaw);
        const right = rightRaw === null || rightRaw === '' ? null : String(rightRaw);
        const changed = (left ?? '') !== (right ?? '');

        return { field, label, left, right, changed };
    });
}

function renderDiff(container, leftVersion, rightVersion, fieldLabels, formattedByVersion) {
    const rows = compareRows(leftVersion.snapshot, rightVersion.snapshot, fieldLabels);
    const changedRows = rows.filter((row) => row.changed);
    const leftFormatted = formattedByVersion[leftVersion.version_no] ?? {};
    const rightFormatted = formattedByVersion[rightVersion.version_no] ?? {};

    if (changedRows.length === 0) {
        container.innerHTML = `
            <p class="splis-version-compare-identical">
                v${leftVersion.version_no} and v${rightVersion.version_no} have no field differences.
            </p>
        `;
        return;
    }

    container.innerHTML = `
        <div class="splis-version-compare-meta">
            <div>
                <p class="splis-version-compare-meta-label">Left</p>
                <p class="splis-version-compare-meta-value">${escapeHtml(leftVersion.label)}</p>
            </div>
            <div>
                <p class="splis-version-compare-meta-label">Right</p>
                <p class="splis-version-compare-meta-value">${escapeHtml(rightVersion.label)}</p>
            </div>
        </div>
        <div class="splis-table-wrap">
            <table class="splis-table splis-version-compare-table">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>v${leftVersion.version_no}</th>
                        <th>v${rightVersion.version_no}</th>
                    </tr>
                </thead>
                <tbody>
                    ${changedRows.map((row) => `
                        <tr class="splis-version-compare-row--changed">
                            <td class="font-medium text-slate-600 dark:text-slate-300">${escapeHtml(row.label)}</td>
                            <td>${formatCellValue(leftFormatted[row.field], row.field)}</td>
                            <td>${formatCellValue(rightFormatted[row.field], row.field)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

export function initAgendaVersionCompare() {
    const root = document.getElementById('agenda-version-compare');
    if (! root) {
        return;
    }

    const modal = document.getElementById('agenda-version-compare-modal');
    const openBtn = document.getElementById('agenda-version-compare-open');
    const leftSelect = document.getElementById('agenda-version-compare-left');
    const rightSelect = document.getElementById('agenda-version-compare-right');
    const selectorsWrap = document.getElementById('agenda-version-compare-selectors');
    const results = document.getElementById('agenda-version-compare-results');

    if (! modal || ! openBtn || ! leftSelect || ! rightSelect || ! results) {
        return;
    }

    const versions = JSON.parse(root.dataset.versions ?? '[]');
    const fieldLabels = JSON.parse(root.dataset.fieldLabels ?? '{}');
    const formattedByVersion = JSON.parse(root.dataset.formatted ?? '{}');

    if (versions.length < 2) {
        return;
    }

    const sortedVersions = [...versions].sort((a, b) => a.version_no - b.version_no);
    const defaultLeft = sortedVersions[sortedVersions.length - 2]?.version_no ?? sortedVersions[0].version_no;
    const defaultRight = sortedVersions[sortedVersions.length - 1]?.version_no ?? sortedVersions[1].version_no;
    const showSelectors = sortedVersions.length > 2;

    if (selectorsWrap) {
        selectorsWrap.hidden = ! showSelectors;
    }

    for (const version of sortedVersions) {
        const option = `<option value="${version.version_no}">${escapeHtml(version.label)}</option>`;
        leftSelect.insertAdjacentHTML('beforeend', option);
        rightSelect.insertAdjacentHTML('beforeend', option);
    }

    leftSelect.value = String(defaultLeft);
    rightSelect.value = String(defaultRight);

    function findVersion(versionNo) {
        return versions.find((version) => version.version_no === Number(versionNo));
    }

    function updateCompare() {
        const left = findVersion(leftSelect.value);
        const right = findVersion(rightSelect.value);

        if (! left || ! right) {
            results.innerHTML = '<p class="splis-version-compare-empty-state">Choose two versions to compare.</p>';
            return;
        }

        if (left.version_no === right.version_no) {
            results.innerHTML = '<p class="splis-version-compare-empty-state">Choose two different versions.</p>';
            return;
        }

        renderDiff(
            results,
            left,
            right,
            fieldLabels,
            formattedByVersion,
        );
    }

    function openModal() {
        modal.hidden = false;
        document.body.classList.add('splis-modal-open');
        updateCompare();
        leftSelect.focus();
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('splis-modal-open');
    }

    openBtn.addEventListener('click', openModal);
    leftSelect.addEventListener('change', updateCompare);
    rightSelect.addEventListener('change', updateCompare);

    modal.querySelectorAll('[data-modal-close]').forEach((element) => {
        element.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && ! modal.hidden) {
            closeModal();
        }
    });
}
