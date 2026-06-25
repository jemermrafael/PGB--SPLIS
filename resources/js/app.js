import { initAccessibility } from './accessibility';
import { initComboboxes } from './combobox';
import { initDashboardSearch } from './dashboard-search';
import { initDropdowns } from './dropdown';
import { initIncomingSearch } from './incoming-search';
import { initResolutionsSearch } from './resolutions-search';

document.addEventListener('DOMContentLoaded', () => {
    initDropdowns();
    initAccessibility();
    initComboboxes();
    initDashboardSearch();
    initResolutionsSearch();
    initIncomingSearch();
});
