import { initAgendaSearch } from './agenda-search';
import { initAgendaForm } from './agenda-form';
import { initAgendaDeadlinePreview } from './agenda-deadline-preview';
import { initAccessibility } from './accessibility';
import { initComboboxes } from './combobox';
import { initDashboardSearch } from './dashboard-search';
import { initDropdowns } from './dropdown';
import { initIncomingSearch } from './incoming-search';
import { initKeywordTags } from './keyword-tags';
import { initMemberMultiSelect, initOrdinanceAttributionMode } from './member-multi-select';
import { initOrdinancesSearch } from './ordinances-search';
import { initResolutionsSearch } from './resolutions-search';

import { initBoardMemberNotifications } from './board-member-notifications';
import { initBoardMemberOrdinancesTable } from './board-member-ordinances';
import {
    initBoardMemberAgendaSearch,
    initBoardMemberDashboardAgenda,
    initBoardMemberDashboardOb,
} from './board-member-dashboard';

document.addEventListener('DOMContentLoaded', () => {
    initDropdowns();
    initAccessibility();
    initComboboxes();
    initAgendaForm();
    initMemberMultiSelect();
    initOrdinanceAttributionMode();
    initKeywordTags();
    initAgendaSearch();
    initAgendaDeadlinePreview();
    initDashboardSearch();
    initOrdinancesSearch();
    initResolutionsSearch();
    initIncomingSearch();
    initBoardMemberNotifications();
    initBoardMemberOrdinancesTable();
    initBoardMemberDashboardAgenda();
    initBoardMemberDashboardOb();
    initBoardMemberAgendaSearch();
});
