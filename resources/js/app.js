import { initAccessibility } from './accessibility';
import { initDashboardSearch } from './dashboard-search';
import { initResolutionsSearch } from './resolutions-search';

document.addEventListener('DOMContentLoaded', () => {
    initAccessibility();
    initDashboardSearch();
    initResolutionsSearch();
});
