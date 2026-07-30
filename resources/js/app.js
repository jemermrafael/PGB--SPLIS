import { initActivityLogDelete } from './activity-log-delete';
import { initAgendaSearch } from './agenda-search';
import { initAgendaForm } from './agenda-form';
import { initAgendaDeadlinePreview } from './agenda-deadline-preview';
import { initAccessibility } from './accessibility';
import { initComboboxes } from './combobox';
import { initDashboardSearch } from './dashboard-search';
import { initDirectorySearch } from './directory-search';
import { initEmailNotificationSettings } from './email-notification-settings';
import { initDropdowns } from './dropdown';
import { initIncomingSearch } from './incoming-search';
import { initKeywordTags } from './keyword-tags';
import { initMemberMultiSelect, initOrdinanceAttributionMode } from './member-multi-select';
import { initOrdinancesSearch } from './ordinances-search';
import { initResolutionsSearch } from './resolutions-search';

import { initAgendaVersionCompare, initResolutionVersionCompare } from './agenda-version-compare';
import { initHeaderNav } from './header-nav';
import { initHeaderNotifications } from './header-notifications';
import { initNotificationsFeed } from './notifications-feed';
import { initBoardMemberOrdinancesTable } from './board-member-ordinances';
import { initAdminBoardMemberOrdinancesSearch } from './admin-board-member-ordinances';
import { initBoardMemberCommitteeReportAgendaSearch } from './board-member-committee-reports';
import { initStaffCommitteeReportsSearch } from './staff-committee-reports';
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
import { initDocumentFolderModal } from './document-folder-modal';
import { initPdfViewerModal } from './pdf-viewer-modal';
import { bindTitleTooltips } from './title-tooltip';
import { initCommitteeReportSummaryMaker } from './committee-report-summary-maker';
import { initMonthlyAttendanceMaker } from './monthly-attendance-maker';

document.addEventListener('DOMContentLoaded', () => {
    initPdfViewerModal();
    bindTitleTooltips(document);
    initDocumentFolderModal();
    initCommitteeReportSummaryMaker();
    initMonthlyAttendanceMaker();
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
    initResolutionVersionCompare();
    initMemberMultiSelect();
    initOrdinanceAttributionMode();
    initKeywordTags();
    initAgendaSearch();
    initAgendaDeadlinePreview();
    initDashboardSearch();
    initDirectorySearch();
    initEmailNotificationSettings();
    initOrdinancesSearch();
    initResolutionsSearch();
    initIncomingSearch();
    initHeaderNotifications();
    initNotificationsFeed();
    initBoardMemberOrdinancesTable();
    initAdminBoardMemberOrdinancesSearch();
    initBoardMemberCommitteeReportAgendaSearch();
    initStaffCommitteeReportsSearch();
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
