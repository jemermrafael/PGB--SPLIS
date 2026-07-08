const POLL_MS = 60000;

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function showToast(notification, stack) {
    const toast = document.createElement('div');
    toast.className = 'splis-toast';
    toast.innerHTML = `
        <p class="splis-toast-title">${escapeHtml(notification.title)}</p>
        ${notification.body ? `<p class="splis-toast-body">${escapeHtml(notification.body)}</p>` : ''}
        ${notification.link ? `<a href="${escapeHtml(notification.link)}" class="splis-toast-link">View agenda</a>` : ''}
        <button type="button" class="splis-toast-close" aria-label="Dismiss">×</button>
    `;

    toast.querySelector('.splis-toast-close')?.addEventListener('click', () => toast.remove());
    if (notification.link) {
        toast.querySelector('.splis-toast-link')?.addEventListener('click', () => {
            fetch(`/notifications/${notification.id}/read`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    Accept: 'application/json',
                },
            }).catch(() => {});
        });
    }

    stack.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('is-visible'));

    window.setTimeout(() => {
        toast.classList.remove('is-visible');
        window.setTimeout(() => toast.remove(), 300);
    }, 12000);
}

export function initBoardMemberNotifications() {
    const stack = document.getElementById('splis-toast-stack');
    if (!stack) {
        return;
    }

    const seen = new Set();

    async function poll() {
        try {
            const response = await fetch('/notifications', {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            for (const notification of data.notifications ?? []) {
                if (seen.has(notification.id)) {
                    continue;
                }

                seen.add(notification.id);
                showToast(notification, stack);
            }
        } catch {
            // ignore transient network errors
        }
    }

    poll();
    window.setInterval(poll, POLL_MS);
}
