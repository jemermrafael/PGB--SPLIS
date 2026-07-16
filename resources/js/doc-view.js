/**
 * Preferred document list/grid mode.
 * Phones/small tablets always start in card (grid) view for readability.
 * Larger screens honor the saved preference, defaulting to list.
 */
export function preferredDocView(storageKey = 'splis-doc-view') {
    if (window.matchMedia('(max-width: 767px)').matches) {
        return 'grid';
    }

    const saved = localStorage.getItem(storageKey);
    if (saved === 'list' || saved === 'grid') {
        return saved;
    }

    return 'list';
}
