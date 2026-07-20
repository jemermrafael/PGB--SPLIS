const MEASURE_LABELS = {
    resolution: {
        number: 'Resolution no.',
        series: 'Series (year)',
        title: 'Resolution title',
        datePassed: 'Date approved',
        dateSigned: 'Date signed by Gov.',
        pdf: 'Resolution PDF URL (legacy GDrive)',
    },
    ordinance: {
        number: 'Ordinance no.',
        series: 'Series year',
        title: 'Ordinance subject',
        datePassed: 'Date enacted',
        dateSigned: 'Date approved',
        pdf: 'Ordinance PDF URL (legacy GDrive)',
    },
    appropriation_ordinance: {
        number: 'Appropriation Ordinance no.',
        series: 'Series year',
        title: 'Appropriation Ordinance subject',
        datePassed: 'Date passed',
        dateSigned: 'Date approved',
        pdf: 'Appropriation Ordinance PDF URL (legacy GDrive)',
    },
};

function setLabel(forId, text) {
    const input = document.getElementById(forId);
    if (!input) {
        return;
    }

    const label = input.closest('div')?.querySelector('label[for="' + forId + '"]');
    if (label) {
        label.textContent = text;
    }
}

export function initAgendaForm() {
    const outputRoot = document.getElementById('agenda-provincial-output');
    if (!outputRoot) {
        return;
    }

    const statusSelect = document.getElementById('status');
    const typeSelect = document.getElementById('reso_ord_ao_type');
    const panels = outputRoot.querySelectorAll('[data-measure-panel]');
    const resolutionOnly = outputRoot.querySelector('[data-resolution-only]');

    function applyMeasureType(type) {
        panels.forEach((panel) => {
            panel.classList.toggle('hidden', !type || panel.dataset.measurePanel !== type);
        });

        if (resolutionOnly) {
            resolutionOnly.classList.toggle('hidden', type !== 'resolution');
        }

        const labels = MEASURE_LABELS[type];
        if (!labels) {
            return;
        }

        setLabel('reso_ord_ao_no', labels.number);
        setLabel('reso_ord_ao_series', labels.series);
        setLabel('resolution_title', labels.title);
        setLabel('date_passed', labels.datePassed);
        setLabel('date_signed_by_gov', labels.dateSigned);
        setLabel('reso_ord_ao_url', labels.pdf);
    }

    function scrollToOutput() {
        outputRoot.scrollIntoView({ behavior: 'smooth', block: 'start' });
        window.setTimeout(() => {
            typeSelect?.focus();
        }, 350);
    }

    typeSelect?.addEventListener('change', () => {
        applyMeasureType(typeSelect.value);
    });

    statusSelect?.addEventListener('change', () => {
        if (statusSelect.value === 'done') {
            scrollToOutput();
        }
    });

    applyMeasureType(typeSelect?.value || '');

    if (statusSelect?.value === 'done' && !typeSelect?.value) {
        outputRoot.classList.add('splis-agenda-output--highlight');
    }
}
