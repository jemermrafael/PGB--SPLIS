export function initEmailNotificationSettings() {
    const root = document.getElementById('email-notification-settings');
    if (!root) {
        return;
    }

    const tabs = [...root.querySelectorAll('[data-email-tab]')];
    const panels = [...root.querySelectorAll('[data-email-panel]')];
    const activeInput = document.getElementById('email-settings-active-tab');
    const testTabInput = root.querySelector('[data-email-test-tab]');

    function activate(tabName) {
        tabs.forEach((tab) => {
            const active = tab.dataset.emailTab === tabName;
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.classList.toggle('ring-2', active);
            tab.classList.toggle('ring-brand-200', active);
        });

        panels.forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.emailPanel !== tabName);
        });

        if (activeInput) {
            activeInput.value = tabName;
        }
        if (testTabInput) {
            testTabInput.value = tabName;
        }

        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabName);
        window.history.replaceState({}, '', url);
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => activate(tab.dataset.emailTab));
    });

    root.querySelector('[data-smtp-preset="gmail"]')?.addEventListener('click', () => {
        const setValue = (id, value) => {
            const el = document.getElementById(id);
            if (el) {
                el.value = value;
            }
        };

        setValue('smtp_mailer', 'smtp');
        setValue('smtp_encryption', 'tls');
        setValue('smtp_host', 'smtp.gmail.com');
        setValue('smtp_port', '587');

        const username = document.getElementById('smtp_username');
        const fromAddress = document.getElementById('smtp_from_address');
        if (username && !username.value.trim()) {
            username.focus();
        }
        if (fromAddress && username?.value.trim() && !fromAddress.value.trim()) {
            fromAddress.value = username.value.trim();
        }

        activate('smtp');
    });
}
