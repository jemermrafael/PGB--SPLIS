import { initActivityLogDelete } from './activity-log-delete';
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

import { initAgendaVersionCompare } from './agenda-version-compare';
import { initHeaderNav } from './header-nav';
import { initHeaderNotifications } from './header-notifications';
import { initNotificationsFeed } from './notifications-feed';
import { initBoardMemberOrdinancesTable } from './board-member-ordinances';
import { initAdminBoardMemberOrdinancesSearch } from './admin-board-member-ordinances';
import {
    initBoardMemberAgendaSearch,
    initBoardMemberDashboardAgenda,
    initBoardMemberDashboardOb,
} from './board-member-dashboard';
import {
    initMunicipalDashboardSearch,
    initMunicipalRequestSearch,
} from './municipal-requests';
import { initAdminAnalytics } from './admin-analytics';
import { initCommitteeMunicipalityMap } from './geographic-analytics';
import { initDragScroll } from './drag-scroll';
import { initTableScrollHints } from './table-scroll-hint';
import { initBoardMemberBulkDelete, initConfirmSubmitForms } from './board-members';
import { initCommitteeMonitoring } from './committee-monitoring';
import { initSessionGuests, initSessionAttendanceSelectAll } from './session-guests';
import { initTermSwitchers } from './term-switcher';
import { initPdfViewerModal } from './pdf-viewer-modal';

document.addEventListener('DOMContentLoaded', () => {
    initPdfViewerModal();
    initDropdowns();
    initHeaderNav();
    initAccessibility();
    initComboboxes();
    initDragScroll();
    initTableScrollHints();
    initConfirmSubmitForms();
    initBoardMemberBulkDelete();
    initAgendaForm();
    initAgendaVersionCompare();
    initMemberMultiSelect();
    initOrdinanceAttributionMode();
    initKeywordTags();
    initAgendaSearch();
    initAgendaDeadlinePreview();
    initDashboardSearch();
    initOrdinancesSearch();
    initResolutionsSearch();
    initIncomingSearch();
    initHeaderNotifications();
    initNotificationsFeed();
    initBoardMemberOrdinancesTable();
    initAdminBoardMemberOrdinancesSearch();
    initBoardMemberDashboardAgenda();
    initBoardMemberDashboardOb();
    initBoardMemberAgendaSearch();
    initMunicipalDashboardSearch();
    initMunicipalRequestSearch();
    initActivityLogDelete();
    initAdminAnalytics();
    initCommitteeMunicipalityMap();
    initCommitteeMonitoring();
    initSessionGuests();
    initSessionAttendanceSelectAll();
    initTermSwitchers();
});
