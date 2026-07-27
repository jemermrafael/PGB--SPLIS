const POLL_MS = 60000;
const POPUP_MS = 8000;
const BROWSER_NOTIFY_PREF_KEY = 'splis-browser-notifications';

let audioContext = null;

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

function ensureAudioContext() {
    if (audioContext) {
        return audioContext;
    }

    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (! Ctx) {
        return null;
    }

    audioContext = new Ctx();
    return audioContext;
}

function playNotificationSound() {
    const ctx = ensureAudioContext();
    if (! ctx) {
        return;
    }

    if (ctx.state === 'suspended') {
        ctx.resume().catch(() => {});
    }

    const now = ctx.currentTime;
    const tones = [
        { frequency: 880, start: 0, duration: 0.12 },
        { frequency: 1174.66, start: 0.14, duration: 0.16 },
    ];

    for (const tone of tones) {
        const oscillator = ctx.createOscillator();
        const gain = ctx.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = tone.frequency;
        oscillator.connect(gain);
        gain.connect(ctx.destination);

        const start = now + tone.start;
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(0.12, start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + tone.duration);

        oscillator.start(start);
        oscillator.stop(start + tone.duration + 0.02);
    }
}

function bindAudioUnlock() {
    const unlock = () => {
        ensureAudioContext()?.resume().catch(() => {});
    };

    document.addEventListener('click', unlock, { once: true, passive: true });
    document.addEventListener('keydown', unlock, { once: true, passive: true });
}

function browserNotificationsSupported() {
    return typeof window.Notification === 'function';
}

function browserNotificationsPreferred() {
    return localStorage.getItem(BROWSER_NOTIFY_PREF_KEY) !== 'off';
}

function setBrowserNotificationsPreferred(enabled) {
    localStorage.setItem(BROWSER_NOTIFY_PREF_KEY, enabled ? 'on' : 'off');
}

async function ensureBrowserNotificationPermission() {
    if (! browserNotificationsSupported()) {
        return 'unsupported';
    }

    if (Notification.permission === 'granted') {
        setBrowserNotificationsPreferred(true);
        return 'granted';
    }

    if (Notification.permission === 'denied') {
        return 'denied';
    }

    try {
        const permission = await Notification.requestPermission();
        if (permission === 'granted') {
            setBrowserNotificationsPreferred(true);
        }
        return permission;
    } catch {
        return Notification.permission;
    }
}

function showBrowserNotification(notification, feedUrl) {
    if (! browserNotificationsSupported()) {
        return;
    }

    if (! browserNotificationsPreferred() || Notification.permission !== 'granted') {
        return;
    }

    // Prefer OS notifications when the tab is in the background; still show when focused
    // so users who leave SPLIS open in another window still get a system alert.
    const title = notification.title || 'SPLIS notification';
    const body = notification.body || '';
    const tag = `splis-notify-${notification.id}`;

    try {
        const browserNote = new Notification(title, {
            body,
            tag,
            renotify: true,
            requireInteraction: false,
        });

        browserNote.onclick = () => {
            window.focus();
            if (notification.link) {
                window.location.assign(notification.link);
            } else if (feedUrl) {
                window.location.assign(feedUrl);
            }
            browserNote.close();
        };
    } catch {
        // Some browsers throw if permission changed mid-session.
    }
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

function linkLabel(notification) {
    return notification.link_label || 'View details';
}

export function initHeaderNotifications() {
    const badge = document.getElementById('splis-notify-badge');
    const listEl = document.getElementById('splis-notify-list');
    const markAllBtn = document.getElementById('splis-notify-mark-all');
    const stack = document.getElementById('splis-toast-stack');
    const wrap = document.querySelector('.splis-notify-wrap');
    const feedUrl = wrap?.dataset.notificationsFeedUrl ?? '/notifications';
    const browserNotifyBtn = document.getElementById('splis-notify-browser-toggle');
    const browserNotifyHint = document.getElementById('splis-notify-browser-hint');

    if (! badge || ! listEl) {
        return;
    }

    bindAudioUnlock();

    /** @type {Map<number, object>} */
    const items = new Map();
    const popupSeen = new Set();
    let isInitialPoll = true;

    const initialNotifications = JSON.parse(wrap?.dataset.initialNotifications ?? '[]');
    const initialCount = Number(wrap?.dataset.initialCount ?? 0);

    function syncBrowserNotifyUi() {
        if (! browserNotifyBtn) {
            return;
        }

        if (! browserNotificationsSupported()) {
            browserNotifyBtn.hidden = true;
            if (browserNotifyHint) {
                browserNotifyHint.hidden = false;
                browserNotifyHint.textContent = 'Browser notifications are not supported in this browser.';
            }
            return;
        }

        browserNotifyBtn.hidden = false;
        const permission = Notification.permission;
        const preferred = browserNotificationsPreferred();

        if (permission === 'granted' && preferred) {
            browserNotifyBtn.textContent = 'Turn off browser notifications';
            browserNotifyBtn.dataset.state = 'on';
            if (browserNotifyHint) {
                browserNotifyHint.hidden = true;
            }
            return;
        }

        if (permission === 'denied') {
            browserNotifyBtn.textContent = 'Browser notifications blocked';
            browserNotifyBtn.dataset.state = 'blocked';
            browserNotifyBtn.disabled = true;
            if (browserNotifyHint) {
                browserNotifyHint.hidden = false;
                browserNotifyHint.textContent = 'Allow notifications for this site in your browser settings, then reload.';
            }
            return;
        }

        browserNotifyBtn.disabled = false;
        browserNotifyBtn.textContent = 'Allow browser notifications';
        browserNotifyBtn.dataset.state = 'off';
        if (browserNotifyHint) {
            browserNotifyHint.hidden = false;
            browserNotifyHint.textContent = 'Get alerts even when SPLIS is in another tab or minimized.';
        }
    }

    browserNotifyBtn?.addEventListener('click', async (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (! browserNotificationsSupported()) {
            return;
        }

        if (Notification.permission === 'granted' && browserNotificationsPreferred()) {
            setBrowserNotificationsPreferred(false);
            syncBrowserNotifyUi();
            return;
        }

        await ensureBrowserNotificationPermission();
        syncBrowserNotifyUi();
    });

    syncBrowserNotifyUi();

    function setBadge(count) {
        const n = Number(count) || 0;
        badge.hidden = n <= 0;
        badge.textContent = n > 99 ? '99+' : String(n);
        if (markAllBtn) {
            markAllBtn.hidden = n <= 0;
        }
    }

    function renderList() {
        const notifications = [...items.values()];

        if (notifications.length === 0) {
            listEl.innerHTML = '<p class="splis-notify-empty">No notifications yet.</p>';
            return;
        }

        listEl.innerHTML = notifications.map((notification) => {
            const unreadClass = notification.unread ? ' is-unread' : '';
            const body = notification.body
                ? `<p class="splis-notify-item-body">${escapeHtml(notification.body)}</p>`
                : '';
            const link = notification.link
                ? `<a href="${escapeHtml(notification.link)}" class="splis-notify-item-link" data-notify-link="${notification.id}">${escapeHtml(linkLabel(notification))}</a>`
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
        }).join('');
    }

    function upsertNotifications(notifications) {
        if (! Array.isArray(notifications)) {
            return;
        }

        const incomingIds = new Set();

        for (const notification of notifications) {
            const id = Number(notification.id);
            incomingIds.add(id);
            items.set(id, { ...notification, id });
        }

        for (const id of [...items.keys()]) {
            if (! incomingIds.has(id)) {
                items.delete(id);
            }
        }
    }

    function showPopup(notification) {
        if (popupSeen.has(notification.id)) {
            return;
        }

        popupSeen.add(notification.id);
        playNotificationSound();
        showBrowserNotification(notification, feedUrl);

        if (! stack) {
            return;
        }

        const toast = document.createElement('div');
        toast.className = 'splis-toast';
        toast.innerHTML = `
            <p class="splis-toast-title">${escapeHtml(notification.title)}</p>
            ${notification.body ? `<p class="splis-toast-body">${escapeHtml(notification.body)}</p>` : ''}
            ${notification.link ? `<a href="${escapeHtml(notification.link)}" class="splis-toast-link" data-popup-link="${notification.id}">${escapeHtml(linkLabel(notification))}</a>` : ''}
            <div class="splis-toast-footer">
                <a href="${escapeHtml(feedUrl)}" class="splis-toast-see-all">See all</a>
            </div>
            <button type="button" class="splis-toast-close" aria-label="Dismiss">×</button>
        `;

        const removeToast = () => {
            toast.classList.remove('is-visible');
            window.setTimeout(() => toast.remove(), 300);
        };

        toast.querySelector('.splis-toast-close')?.addEventListener('click', async () => {
            removeToast();
            await dismissNotification(notification.id);
        });

        toast.querySelector('[data-popup-link]')?.addEventListener('click', () => {
            markRead(notification.id).then((data) => {
                if (data) {
                    const current = items.get(notification.id);
                    if (current) {
                        current.unread = false;
                        renderList();
                    }
                    if (typeof data.count === 'number') {
                        setBadge(data.count);
                    }
                }
            });
        });

        stack.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('is-visible'));
        window.setTimeout(removeToast, POPUP_MS);
    }

    async function dismissNotification(id) {
        const notificationId = Number(id);
        const data = await markRead(notificationId);

        if (! data?.ok) {
            return;
        }

        const current = items.get(notificationId);
        if (current) {
            current.unread = false;
            renderList();
        }

        if (typeof data.count === 'number') {
            setBadge(data.count);
        }
    }

    listEl.addEventListener('click', async (event) => {
        const dismissBtn = event.target.closest('[data-notify-dismiss]');
        if (dismissBtn) {
            event.preventDefault();
            event.stopPropagation();
            await dismissNotification(Number(dismissBtn.dataset.notifyDismiss));
            return;
        }

        const link = event.target.closest('[data-notify-link]');
        if (link) {
            const id = Number(link.dataset.notifyLink);
            markRead(id).then((data) => {
                const current = items.get(id);
                if (current) {
                    current.unread = false;
                    renderList();
                }
                if (data && typeof data.count === 'number') {
                    setBadge(data.count);
                }
            });
        }
    });

    markAllBtn?.addEventListener('click', async (event) => {
        event.preventDefault();
        event.stopPropagation();
        const data = await markAllRead();
        for (const notification of items.values()) {
            notification.unread = false;
        }
        renderList();
        setBadge(data?.count ?? 0);
    });

    async function poll() {
        try {
            const response = await fetch('/notifications/feed?limit=10', {
                headers: { Accept: 'application/json' },
            });

            if (! response.ok) {
                return;
            }

            const data = await response.json();
            const notifications = Array.isArray(data.notifications) ? data.notifications : null;

            if (notifications === null) {
                return;
            }

            const previousIds = new Set(items.keys());

            upsertNotifications(notifications);
            setBadge(data.count);
            renderList();

            for (const notification of notifications) {
                if (! notification.unread) {
                    continue;
                }

                const isNew = ! previousIds.has(Number(notification.id));
                if (isInitialPoll) {
                    popupSeen.add(notification.id);
                    continue;
                }

                if (isNew) {
                    showPopup(notification);
                }
            }

            isInitialPoll = false;
        } catch {
            // ignore transient network errors
        }
    }

    if (Array.isArray(initialNotifications) && initialNotifications.length > 0) {
        upsertNotifications(initialNotifications);
        setBadge(initialCount);
        renderList();
        for (const notification of initialNotifications) {
            popupSeen.add(Number(notification.id));
        }
    }

    poll();
    window.setInterval(poll, POLL_MS);
}

/** @deprecated Use initHeaderNotifications */
export function initBoardMemberNotifications() {
    initHeaderNotifications();
}
