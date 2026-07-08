import { bindTitleTooltips } from './title-tooltip';

export function initBoardMemberOrdinancesTable() {
    const root = document.getElementById('bm-ordinances-table');

    if (!root) {
        return;
    }

    bindTitleTooltips(root);
}
