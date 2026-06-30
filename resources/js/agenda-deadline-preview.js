export function initAgendaDeadlinePreview() {
    const root = document.getElementById('agenda-deadline-preview');
    if (!root) {
        return;
    }

    const previewUrl = root.dataset.previewUrl;
    const dateInput = document.getElementById('date_received');
    const prescribedSelect = document.getElementById('prescribed_days');
    const statusSelect = document.getElementById('status');
    const dueDateEl = root.querySelector('[data-preview-due-date]');
    const daysLeftEl = root.querySelector('[data-preview-days-left]');
    const toneEl = root.querySelector('[data-preview-tone]');

    if (!previewUrl || !dateInput || !prescribedSelect) {
        return;
    }

    let debounceTimer;

    const update = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchPreview, 250);
    };

    dateInput.addEventListener('change', update);
    dateInput.addEventListener('input', update);
    prescribedSelect.addEventListener('change', update);
    statusSelect?.addEventListener('change', update);

    fetchPreview();

    async function fetchPreview() {
        const params = new URLSearchParams({
            date_received: dateInput.value || '',
            prescribed_days: prescribedSelect.value || '',
            status: statusSelect?.value || '',
        });

        root.classList.add('opacity-60');

        try {
            const response = await fetch(`${previewUrl}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Preview failed');
            }

            const payload = await response.json();
            renderPreview(payload);
        } catch {
            renderPreview({
                due_date: null,
                days_left_label: '—',
                tone: 'none',
            });
        } finally {
            root.classList.remove('opacity-60');
        }
    }

    function renderPreview(payload) {
        if (dueDateEl) {
            if (!payload.due_date) {
                dueDateEl.textContent = 'No due date';
            } else {
                const date = new Date(payload.due_date);
                dueDateEl.textContent = Number.isNaN(date.getTime())
                    ? payload.due_date
                    : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
            }
        }

        if (daysLeftEl) {
            daysLeftEl.textContent = payload.days_left_label || '—';
            daysLeftEl.className = `splis-agenda-days splis-agenda-days--${payload.tone || 'none'} splis-agenda-days--lg`;
        }

        if (toneEl) {
            toneEl.dataset.tone = payload.tone || 'none';
        }
    }
}
