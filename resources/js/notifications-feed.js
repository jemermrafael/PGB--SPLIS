function csrfHeaders() {
    return {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        Accept: 'application/json',
    };
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function renderNotificationItem(notification) {
    const unreadClass = notification.unread ? ' is-unread' : '';
    const body = notification.body
        ? `<p class="splis-notify-item-body">${escapeHtml(notification.body)}</p>`
        : '';
    const link = notification.link
        ? `<a href="${escapeHtml(notification.link)}" class="splis-notify-item-link" data-notify-link="${notification.id}">${escapeHtml(notification.link_label || 'View details')}</a>`
        : '';
    const dismiss = notification.unread
        ? `<button type="button" class="splis-notify-item-dismiss" data-notify-dismiss="${notification.id}" aria-label="Dismiss">×</button>`
        : '<span class="splis-notify-read-label">Read</span>';

    return `
        <article class="splis-notify-item${unreadClass}" data-notify-id="${notification.id}">
            <div class="splis-notify-item-main">
                <p class="splis-notify-item-title">${escapeHtml(notification.title)}</p>
                ${body}
                <p class="splis-notify-item-time">${escapeHtml(notification.created_at ?? '')}</p>
                ${link}
            </div>
            ${dismiss}
        </article>
    `;
}

async function markRead(id) {
    try {
        const response = await fetch(`/notifications/${id}/read`, {
            method: 'POST',
            headers: csrfHeaders(),
        });

        if (! response.ok) {
            return null;
        }

        return response.json();
    } catch {
        return null;
    }
}

async function markAllRead() {
    try {
        const response = await fetch('/notifications/read-all', {
            method: 'POST',
            headers: csrfHeaders(),
        });

        if (! response.ok) {
            return null;
        }

        return response.json();
    } catch {
        return null;
    }
}

function markItemReadInDom(item) {
    item.classList.remove('is-unread');
    const dismiss = item.querySelector('[data-notify-dismiss]');
    if (dismiss) {
        dismiss.replaceWith(Object.assign(document.createElement('span'), {
            className: 'splis-notify-read-label',
            textContent: 'Read',
        }));
    }
}

export function initNotificationsFeed() {
    const root = document.getElementById('notifications-feed');
    const listEl = document.getElementById('notifications-feed-list');
    const loadMoreBtn = document.getElementById('notifications-feed-load-more');
    const markAllBtn = document.getElementById('notifications-feed-mark-all');

    if (! root || ! listEl) {
        return;
    }

    let nextBeforeId = root.dataset.nextBeforeId ? Number(root.dataset.nextBeforeId) : null;
    let hasMore = root.dataset.hasMore === '1';
    let loading = false;

    function ensureFooter() {
        let footer = root.querySelector('.splis-notify-feed-footer');

        if (! hasMore) {
            footer?.remove();
            return;
        }

        if (! footer) {
            footer = document.createElement('div');
            footer.className = 'splis-notify-feed-footer';
            footer.innerHTML = `
                <button type="button" id="notifications-feed-load-more" class="splis-btn-secondary w-full">
                    See previous notifications
                </button>
            `;
            root.appendChild(footer);
            footer.querySelector('#notifications-feed-load-more')?.addEventListener('click', loadMore);
        }
    }

    async function loadMore() {
        if (! hasMore || loading || nextBeforeId === null) {
            return;
        }

        loading = true;
        loadMoreBtn.disabled = true;
        loadMoreBtn.textContent = 'Loading…';

        try {
            const url = new URL('/notifications', window.location.origin);
            url.searchParams.set('limit', '10');
            url.searchParams.set('before_id', String(nextBeforeId));

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
            });

            if (! response.ok) {
                return;
            }

            const data = await response.json();
            const notifications = Array.isArray(data.notifications) ? data.notifications : [];

            if (notifications.length === 0) {
                hasMore = false;
                ensureFooter();
                return;
            }

            const empty = listEl.querySelector('.splis-notify-empty');
            empty?.remove();

            listEl.insertAdjacentHTML('beforeend', notifications.map(renderNotificationItem).join(''));

            hasMore = Boolean(data.has_more);
            nextBeforeId = data.next_before_id ? Number(data.next_before_id) : null;
            root.dataset.hasMore = hasMore ? '1' : '0';
            root.dataset.nextBeforeId = nextBeforeId ?? '';
            ensureFooter();
        } finally {
            loading = false;
            const btn = document.getElementById('notifications-feed-load-more');
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'See previous notifications';
            }
        }
    }

    listEl.addEventListener('click', async (event) => {
        const dismissBtn = event.target.closest('[data-notify-dismiss]');
        if (dismissBtn) {
            event.preventDefault();
            const id = Number(dismissBtn.dataset.notifyDismiss);
            const data = await markRead(id);
            if (data?.ok) {
                const item = listEl.querySelector(`[data-notify-id="${id}"]`);
                if (item) {
                    markItemReadInDom(item);
                }
            }
            return;
        }

        const link = event.target.closest('[data-notify-link]');
        if (link) {
            const id = Number(link.dataset.notifyLink);
            markRead(id).then((data) => {
                if (data?.ok) {
                    const item = listEl.querySelector(`[data-notify-id="${id}"]`);
                    if (item) {
                        markItemReadInDom(item);
                    }
                }
            });
        }
    });

    markAllBtn?.addEventListener('click', async () => {
        const data = await markAllRead();
        if (! data?.ok) {
            return;
        }

        listEl.querySelectorAll('.splis-notify-item.is-unread').forEach((item) => {
            markItemReadInDom(item);
        });
        markAllBtn.remove();
    });

    loadMoreBtn?.addEventListener('click', loadMore);
}
