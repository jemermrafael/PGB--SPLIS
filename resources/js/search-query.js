export function normalizeKeyword(value) {
    return String(value ?? '').replace(/^,+|,+$/g, '').trim();
}

export function applyKeywordFromQuery(form) {
    const keyword = normalizeKeyword(new URLSearchParams(window.location.search).get('keyword'));

    if (!keyword) {
        return false;
    }

    const input = form.querySelector('[name="keyword"]');

    if (!input) {
        return false;
    }

    input.value = keyword;

    return true;
}
