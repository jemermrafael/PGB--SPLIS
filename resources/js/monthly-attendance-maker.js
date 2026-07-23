function debounce(fn, ms) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), ms);
    };
}

export function initMonthlyAttendanceMaker() {
    const root = document.getElementById('att-monthly-maker');
    if (!root) {
        return;
    }

    const form = root.querySelector('form[data-att-monthly-form]');
    const saveStatus = document.getElementById('att-monthly-save-status');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        ?? form?.querySelector('input[name="_token"]')?.value
        ?? '';

    function setStatus(message, isError = false) {
        if (!saveStatus) {
            return;
        }
        saveStatus.textContent = message;
        saveStatus.classList.toggle('text-red-600', isError);
        saveStatus.classList.toggle('dark:text-red-400', isError);
        saveStatus.classList.toggle('text-slate-500', !isError);
    }

    const saveSheet = debounce(async () => {
        if (!form) {
            return;
        }

        setStatus('Saving…');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: new FormData(form),
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const validationMessage = data.errors
                    ? Object.values(data.errors).flat().find(Boolean)
                    : null;
                throw new Error(validationMessage ?? data.message ?? 'Save failed.');
            }

            setStatus('Saved');
        } catch (error) {
            setStatus(error.message || 'Save failed.', true);
        }
    }, 500);

    root.addEventListener('input', (event) => {
        if (event.target.closest('form[data-att-monthly-form]')) {
            saveSheet();
        }
    });

    root.addEventListener('change', (event) => {
        if (event.target.closest('form[data-att-monthly-form]')) {
            saveSheet();
        }
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        saveSheet();
    });
}
