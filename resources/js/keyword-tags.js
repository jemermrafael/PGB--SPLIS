function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function parseTags(value) {
    return String(value ?? '')
        .split(',')
        .map((part) => part.trim().replace(/^,+|,+$/g, ''))
        .filter(Boolean);
}

function tagsToValue(tags) {
    return tags.join(', ');
}

const DISPLAY_LIMIT = 200;
let keywordCache = null;
let keywordCachePromise = null;

async function loadKeywords(url) {
    if (keywordCache) {
        return keywordCache;
    }

    if (!keywordCachePromise) {
        keywordCachePromise = fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Failed to load keywords');
                }

                return response.json();
            })
            .then((payload) => {
                keywordCache = payload.data || [];
                return keywordCache;
            })
            .catch(() => {
                keywordCachePromise = null;
                return [];
            });
    }

    return keywordCachePromise;
}

export function initKeywordTags() {
    document.querySelectorAll('[data-keyword-tags]').forEach((root) => {
        const chipsEl = root.querySelector('[data-keyword-chips]');
        const input = root.querySelector('[data-keyword-input]');
        const hidden = root.querySelector('[data-keyword-hidden]');
        const trigger = root.querySelector('[data-keyword-trigger]');
        const panel = root.querySelector('[data-keyword-panel]');
        const list = root.querySelector('[data-keyword-list]');
        const panelTitle = root.querySelector('[data-keyword-panel-title]');
        const keywordsUrl = root.dataset.keywordsUrl;

        if (!chipsEl || !input || !hidden || !panel || !list || !keywordsUrl) {
            return;
        }

        const maxLength = Number(root.dataset.maxLength || 150);
        let options = [];
        let optionsLoaded = false;
        let optionsLoading = false;
        let tags = parseTags(hidden.value);
        let filtered = [];
        let activeIndex = -1;

        renderChips();

        input.addEventListener('focus', () => {
            openPanel();
        });

        input.addEventListener('input', () => {
            filterOptions();
            openPanel();
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === ',') {
                event.preventDefault();
                addTag(input.value.replace(/,+$/, ''));
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                if (panel.classList.contains('open') && activeIndex >= 0) {
                    addTag(filtered[activeIndex]);
                } else {
                    addTag(input.value.replace(/,+$/, ''));
                }
                return;
            }

            if (event.key === 'Backspace' && input.value === '' && tags.length > 0) {
                removeTag(tags.length - 1);
                return;
            }

            if (!panel.classList.contains('open')) {
                if (event.key === 'ArrowDown') {
                    openPanel();
                    event.preventDefault();
                }
                return;
            }

            if (event.key === 'Escape') {
                closePanel();
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                highlightOption(Math.min(activeIndex + 1, Math.min(filtered.length, DISPLAY_LIMIT) - 1));
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                highlightOption(Math.max(activeIndex - 1, 0));
            }
        });

        trigger?.addEventListener('click', () => {
            if (panel.classList.contains('open')) {
                closePanel();
            } else {
                openPanel();
                input.focus();
            }
        });

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) {
                closePanel();
            }
        });

        async function ensureOptions() {
            if (optionsLoaded || optionsLoading) {
                return;
            }

            optionsLoading = true;
            list.innerHTML = '<p class="splis-keyword-tags-empty">Loading keywords…</p>';

            options = await loadKeywords(keywordsUrl);
            optionsLoaded = true;
            optionsLoading = false;

            if (panelTitle) {
                panelTitle.textContent = `All used keywords (${options.length.toLocaleString()})`;
            }

            filterOptions();
        }

        function openPanel() {
            panel.classList.add('open');
            root.classList.add('is-open');
            ensureOptions().then(() => filterOptions());
        }

        function closePanel() {
            panel.classList.remove('open');
            root.classList.remove('is-open');
            activeIndex = -1;
        }

        function syncHidden() {
            hidden.value = tagsToValue(tags);
        }

        function hasTag(value) {
            const key = value.trim().toLowerCase();
            return tags.some((tag) => tag.toLowerCase() === key);
        }

        function canAddTag(value) {
            const trimmed = value.trim();
            if (!trimmed || hasTag(trimmed)) {
                return false;
            }

            return tagsToValue([...tags, trimmed]).length <= maxLength;
        }

        function addTag(raw) {
            const value = String(raw ?? '').trim();
            if (!canAddTag(value)) {
                return;
            }

            tags.push(value);
            syncHidden();
            renderChips();
            input.value = '';
            filterOptions();
        }

        function removeTag(index) {
            tags.splice(index, 1);
            syncHidden();
            renderChips();
            filterOptions();
        }

        function renderChips() {
            if (!tags.length) {
                chipsEl.innerHTML = '';
                return;
            }

            chipsEl.innerHTML = tags.map((tag, index) => `
                <span class="group/tag splis-keyword-tag">
                    <span class="splis-keyword-tag-label">${escapeHtml(tag)}</span>
                    <button
                        type="button"
                        class="splis-keyword-tag-remove"
                        data-index="${index}"
                        aria-label="Remove ${escapeHtml(tag)}"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </span>
            `).join('');

            chipsEl.querySelectorAll('[data-index]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    removeTag(Number(button.dataset.index));
                    input.focus();
                });
            });
        }

        function filterOptions() {
            if (!optionsLoaded) {
                return;
            }

            const term = input.value.trim().toLowerCase();
            const available = options.filter((option) => !hasTag(option));
            filtered = term === ''
                ? available
                : available.filter((option) => option.toLowerCase().includes(term));

            renderOptions(filtered, term);
        }

        function renderOptions(items, term = '') {
            activeIndex = -1;

            if (!items.length) {
                list.innerHTML = '<p class="splis-keyword-tags-empty">No matching keywords</p>';
                return;
            }

            const display = items.slice(0, DISPLAY_LIMIT);
            const remaining = items.length - display.length;

            list.innerHTML = display.map((item, index) => (
                `<button type="button" class="splis-keyword-tags-option" data-index="${index}" role="option">${escapeHtml(item)}</button>`
            )).join('') + (remaining > 0
                ? `<p class="splis-keyword-tags-more">${remaining.toLocaleString()} more — ${term ? 'refine your search' : 'type to filter'}</p>`
                : '');

            list.querySelectorAll('.splis-keyword-tags-option').forEach((button) => {
                button.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    addTag(display[Number(button.dataset.index)]);
                });
            });
        }

        function highlightOption(index) {
            const buttons = list.querySelectorAll('.splis-keyword-tags-option');
            buttons.forEach((button, i) => {
                button.classList.toggle('is-active', i === index);
            });
            activeIndex = index;
            buttons[index]?.scrollIntoView({ block: 'nearest' });
        }
    });
}
