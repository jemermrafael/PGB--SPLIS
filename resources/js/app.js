import { initAgendaSearch } from './agenda-search';
import { initAgendaDeadlinePreview } from './agenda-deadline-preview';
import { initAccessibility } from './accessibility';
import { initComboboxes } from './combobox';
import { initDashboardSearch } from './dashboard-search';
import { initDropdowns } from './dropdown';
import { initIncomingSearch } from './incoming-search';
import { initKeywordTags } from './keyword-tags';
import { initResolutionsSearch } from './resolutions-search';

document.addEventListener('DOMContentLoaded', () => {
    initDropdowns();
    initAccessibility();
    initComboboxes();
    initKeywordTags();
    initAgendaSearch();
    initAgendaDeadlinePreview();
    initDashboardSearch();
    initResolutionsSearch();
    initIncomingSearch();
});
