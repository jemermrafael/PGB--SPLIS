<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BoardMemberAgendaController;
use App\Http\Controllers\BoardMemberAgendaSearchController;
use App\Http\Controllers\BoardMemberCommitteeAgendaController;
use App\Http\Controllers\BoardMemberObSearchController;
use App\Http\Controllers\MunicipalRequestController;
use App\Http\Controllers\MunicipalRequestSearchController;
use App\Http\Controllers\MyOrdinanceController;
use App\Http\Controllers\AppropriationOrdinanceController;
use App\Http\Controllers\BoardMemberOrdinanceReportController;
use App\Http\Controllers\CommitteeController;
use App\Http\Controllers\CommitteeMonitoringController;
use App\Http\Controllers\CommitteeTermController;
use App\Http\Controllers\LegislativeSessionController;
use App\Http\Controllers\ObAgendaPoolController;
use App\Http\Controllers\ObDocumentController;
use App\Http\Controllers\AgendaDeadlinePreviewController;
use App\Http\Controllers\AgendaItemController;
use App\Http\Controllers\AgendaSearchController;
use App\Http\Controllers\AdminAnalyticsController;
use App\Http\Controllers\AdminAnalyticsMapController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DatabaseBackupController;
use App\Http\Controllers\Admin\DataSyncController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardSearchController;
use App\Http\Controllers\IncomingDocumentController;
use App\Http\Controllers\IncomingKeywordController;
use App\Http\Controllers\IncomingSearchController;
use App\Http\Controllers\OrdinanceController;
use App\Http\Controllers\OrdinanceSearchController;
use App\Http\Controllers\ResolutionController;
use App\Http\Controllers\ResolutionPdfController;
use App\Http\Controllers\ResolutionSearchController;
use App\Http\Controllers\ReferenceMaterialController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BoardMemberController;
use App\Http\Controllers\SessionAttendanceController;
use App\Http\Controllers\UserNotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/admin/analytics', AdminAnalyticsController::class)->name('admin.analytics.index');
    Route::get('/admin/analytics/municipality-map', AdminAnalyticsMapController::class)->name('admin.analytics.municipality-map');
    Route::get('/dashboard/documents/search', DashboardSearchController::class)->name('dashboard.documents.search');

    Route::get('/notifications/all', [UserNotificationController::class, 'feed'])->name('notifications.feed');
    Route::get('/notifications', [UserNotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [UserNotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [UserNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::delete('/activity-logs/{activityLog}', [ActivityLogController::class, 'destroy'])->name('activity-logs.destroy');

    Route::get('/my-ordinances', [MyOrdinanceController::class, 'index'])->name('board-member.ordinances.index');
    Route::get('/all-ordinances', [MyOrdinanceController::class, 'all'])->name('board-member.ordinances.all');
    Route::get('/all-ordinances/search', [MyOrdinanceController::class, 'allSearch'])->name('board-member.ordinances.all.search');

    Route::get('/my-agenda', [BoardMemberAgendaController::class, 'index'])->name('board-member.agenda.index');
    Route::get('/my-agenda/search', BoardMemberAgendaSearchController::class)->name('board-member.agenda.search');
    Route::get('/my-agenda/committees/{committee}', [BoardMemberCommitteeAgendaController::class, 'show'])->name('board-member.agenda.committee');
    Route::get('/dashboard/ob/search', BoardMemberObSearchController::class)->name('board-member.dashboard.ob.search');

    Route::get('/my-requests', [MunicipalRequestController::class, 'index'])->name('municipal.requests.index');
    Route::get('/my-requests/search', MunicipalRequestSearchController::class)->name('municipal.requests.search');
    Route::get('/my-requests/{agenda}', [MunicipalRequestController::class, 'show'])->name('municipal.requests.show');

    Route::get('/admin/board-member-ordinances', [BoardMemberOrdinanceReportController::class, 'index'])->name('admin.board-member-ordinances');

    Route::get('/resolutions', [ResolutionController::class, 'index'])->name('resolutions.index');
    Route::get('/resolutions/search', ResolutionSearchController::class)->name('resolutions.search');
    Route::get('/resolutions/create', [ResolutionController::class, 'create'])->name('resolutions.create');
    Route::post('/resolutions', [ResolutionController::class, 'store'])->name('resolutions.store');
    Route::get('/resolutions/trash', [ResolutionController::class, 'trash'])->name('resolutions.trash');
    Route::get('/resolutions/legacy/{id}', [ResolutionController::class, 'showLegacy'])->name('resolutions.show-legacy');
    Route::get('/resolutions/pdf/{series}/{resolutionNo}', ResolutionPdfController::class)
        ->where('resolutionNo', '.*')
        ->name('resolutions.pdf');
    Route::get('/resolutions/{resolution}', [ResolutionController::class, 'show'])->name('resolutions.show')->withTrashed();
    Route::get('/resolutions/{resolution}/edit', [ResolutionController::class, 'edit'])->name('resolutions.edit');
    Route::put('/resolutions/{resolution}', [ResolutionController::class, 'update'])->name('resolutions.update');
    Route::delete('/resolutions/{resolution}', [ResolutionController::class, 'destroy'])->name('resolutions.destroy');
    Route::post('/resolutions/{resolution}/restore', [ResolutionController::class, 'restore'])->name('resolutions.restore')->withTrashed();
    Route::delete('/resolutions/{resolution}/force', [ResolutionController::class, 'forceDestroy'])->name('resolutions.force-destroy')->withTrashed();

    Route::get('/references', [ReferenceMaterialController::class, 'index'])->name('references.index');
    Route::get('/references/create', [ReferenceMaterialController::class, 'create'])->name('references.create');
    Route::post('/references', [ReferenceMaterialController::class, 'store'])->name('references.store');
    Route::get('/references/{reference}', [ReferenceMaterialController::class, 'show'])->name('references.show');
    Route::get('/references/{reference}/download', [ReferenceMaterialController::class, 'download'])->name('references.download');
    Route::get('/references/{reference}/versions/{version}/download', [ReferenceMaterialController::class, 'downloadVersion'])->name('references.versions.download');
    Route::get('/references/{reference}/edit', [ReferenceMaterialController::class, 'edit'])->name('references.edit');
    Route::put('/references/{reference}', [ReferenceMaterialController::class, 'update'])->name('references.update');
    Route::post('/references/{reference}/archive', [ReferenceMaterialController::class, 'archive'])->name('references.archive');
    Route::post('/references/{reference}/restore', [ReferenceMaterialController::class, 'restore'])->name('references.restore');
    Route::delete('/references/{reference}', [ReferenceMaterialController::class, 'destroy'])->name('references.destroy');

    Route::middleware('incoming.enabled')->group(function () {
        Route::get('/incoming', [IncomingDocumentController::class, 'index'])->name('incoming.index');
        Route::get('/incoming/search', IncomingSearchController::class)->name('incoming.search');
        Route::get('/incoming/keywords', IncomingKeywordController::class)->name('incoming.keywords');
        Route::get('/incoming/create', [IncomingDocumentController::class, 'create'])->name('incoming.create');
        Route::post('/incoming', [IncomingDocumentController::class, 'store'])->name('incoming.store');
        Route::get('/incoming/resolutions/search', [IncomingDocumentController::class, 'searchResolutions'])->name('incoming.resolutions.search');
        Route::get('/incoming/{incoming}/publish', [IncomingDocumentController::class, 'publish'])->name('incoming.publish');
        Route::post('/incoming/{incoming}/publish', [IncomingDocumentController::class, 'publishStore'])->name('incoming.publish.store');
        Route::get('/incoming/{incoming}', [IncomingDocumentController::class, 'show'])->name('incoming.show');
        Route::get('/incoming/{incoming}/edit', [IncomingDocumentController::class, 'edit'])->name('incoming.edit');
        Route::put('/incoming/{incoming}', [IncomingDocumentController::class, 'update'])->name('incoming.update');
        Route::post('/incoming/{incoming}/link', [IncomingDocumentController::class, 'link'])->name('incoming.link');
    });

    Route::get('/ordinances', [OrdinanceController::class, 'index'])->name('ordinances.index');
    Route::get('/ordinances/search', OrdinanceSearchController::class)->name('ordinances.search');
    Route::get('/ordinances/create', [OrdinanceController::class, 'create'])->name('ordinances.create');
    Route::post('/ordinances', [OrdinanceController::class, 'store'])->name('ordinances.store');
    Route::get('/ordinances/{ordinance}', [OrdinanceController::class, 'show'])->name('ordinances.show');
    Route::get('/ordinances/{ordinance}/edit', [OrdinanceController::class, 'edit'])->name('ordinances.edit');
    Route::put('/ordinances/{ordinance}', [OrdinanceController::class, 'update'])->name('ordinances.update');
    Route::delete('/ordinances/{ordinance}', [OrdinanceController::class, 'destroy'])->name('ordinances.destroy');

    Route::get('/appropriation-ordinances', [AppropriationOrdinanceController::class, 'index'])->name('appropriation-ordinances.index');
    Route::get('/appropriation-ordinances/create', [AppropriationOrdinanceController::class, 'create'])->name('appropriation-ordinances.create');
    Route::post('/appropriation-ordinances', [AppropriationOrdinanceController::class, 'store'])->name('appropriation-ordinances.store');
    Route::get('/appropriation-ordinances/{appropriationOrdinance}', [AppropriationOrdinanceController::class, 'show'])->name('appropriation-ordinances.show');
    Route::get('/appropriation-ordinances/{appropriationOrdinance}/edit', [AppropriationOrdinanceController::class, 'edit'])->name('appropriation-ordinances.edit');
    Route::put('/appropriation-ordinances/{appropriationOrdinance}', [AppropriationOrdinanceController::class, 'update'])->name('appropriation-ordinances.update');
    Route::delete('/appropriation-ordinances/{appropriationOrdinance}', [AppropriationOrdinanceController::class, 'destroy'])->name('appropriation-ordinances.destroy');

    Route::get('/agenda', [AgendaItemController::class, 'index'])->name('agenda.index');
    Route::get('/agenda/search', AgendaSearchController::class)->name('agenda.search');
    Route::get('/agenda/preview-deadline', AgendaDeadlinePreviewController::class)->name('agenda.preview-deadline');
    Route::get('/agenda/create', [AgendaItemController::class, 'create'])->name('agenda.create');
    Route::post('/agenda', [AgendaItemController::class, 'store'])->name('agenda.store');
    Route::get('/agenda/{agenda}', [AgendaItemController::class, 'show'])->name('agenda.show');
    Route::get('/agenda/{agenda}/edit', [AgendaItemController::class, 'edit'])->name('agenda.edit');
    Route::put('/agenda/{agenda}', [AgendaItemController::class, 'update'])->name('agenda.update');
    Route::delete('/agenda/{agenda}', [AgendaItemController::class, 'destroy'])->name('agenda.destroy');
    Route::post('/agenda/{agenda}/promote-incoming', [AgendaItemController::class, 'promote'])->name('agenda.promote-incoming');
    Route::post('/agenda/{agenda}/unlink-incoming', [AgendaItemController::class, 'unlinkIncoming'])->name('agenda.unlink-incoming');
    Route::post('/agenda/{agenda}/unlink-resolution', [AgendaItemController::class, 'unlinkResolution'])->name('agenda.unlink-resolution');
    Route::post('/agenda/{agenda}/add-to-order-of-business', [AgendaItemController::class, 'addToOrderOfBusiness'])->name('agenda.add-to-order-of-business');
    Route::delete('/agenda/{agenda}/versions/{version}', [AgendaItemController::class, 'destroyVersion'])->name('agenda.versions.destroy');

    Route::get('/committees', [CommitteeController::class, 'index'])->name('committees.index');
    Route::get('/committees/create', [CommitteeController::class, 'create'])->name('committees.create');
    Route::post('/committees', [CommitteeController::class, 'store'])->name('committees.store');
    Route::get('/committees/{committee}', [CommitteeController::class, 'show'])->name('committees.show');
    Route::get('/committees/{committee}/edit', [CommitteeController::class, 'edit'])->name('committees.edit');
    Route::put('/committees/{committee}', [CommitteeController::class, 'update'])->name('committees.update');
    Route::delete('/committees/{committee}', [CommitteeController::class, 'destroy'])->name('committees.destroy');
    Route::get('/committee-monitoring', [CommitteeMonitoringController::class, 'index'])->name('committee-monitoring.index');

    Route::get('/board-members', [BoardMemberController::class, 'index'])->name('board-members.index');
    Route::get('/board-members/create', [BoardMemberController::class, 'create'])->name('board-members.create');
    Route::post('/board-members', [BoardMemberController::class, 'store'])->name('board-members.store');
    Route::get('/board-members/{boardMember}', [BoardMemberController::class, 'show'])->name('board-members.show');
    Route::get('/board-members/{boardMember}/edit', [BoardMemberController::class, 'edit'])->name('board-members.edit');
    Route::put('/board-members/{boardMember}', [BoardMemberController::class, 'update'])->name('board-members.update');
    Route::delete('/board-members/{boardMember}', [BoardMemberController::class, 'destroy'])->name('board-members.destroy');

    Route::get('/committee-terms', [CommitteeTermController::class, 'index'])->name('committee-terms.index');
    Route::get('/committee-terms/create', [CommitteeTermController::class, 'create'])->name('committee-terms.create');
    Route::post('/committee-terms', [CommitteeTermController::class, 'store'])->name('committee-terms.store');
    Route::get('/committee-terms/{committeeTerm}/edit', [CommitteeTermController::class, 'edit'])->name('committee-terms.edit');
    Route::put('/committee-terms/{committeeTerm}', [CommitteeTermController::class, 'update'])->name('committee-terms.update');
    Route::delete('/committee-terms/{committeeTerm}', [CommitteeTermController::class, 'destroy'])->name('committee-terms.destroy');

    Route::prefix('order-of-business')->name('ob.')->group(function () {
        Route::get('/', [LegislativeSessionController::class, 'index'])->name('sessions.index');
        Route::get('/create', [LegislativeSessionController::class, 'create'])->name('sessions.create');
        Route::post('/', [LegislativeSessionController::class, 'store'])->name('sessions.store');
        Route::get('/{legislativeSession}/document/maker', [ObDocumentController::class, 'maker'])->name('document.maker');
        Route::get('/{legislativeSession}/document/print', [ObDocumentController::class, 'print'])->name('document.print');
        Route::put('/{legislativeSession}/document', [ObDocumentController::class, 'update'])->name('document.update');
        Route::get('/{legislativeSession}/document/agenda-pool', ObAgendaPoolController::class)->name('document.agenda-pool');
        Route::post('/{legislativeSession}/document/blocks', [ObDocumentController::class, 'storeBlock'])->name('document.blocks.store');
        Route::put('/{legislativeSession}/document/blocks/reorder', [ObDocumentController::class, 'reorderBlocks'])->name('document.blocks.reorder');
        Route::post('/{legislativeSession}/document/blocks/from-agenda', [ObDocumentController::class, 'addFromAgenda'])->name('document.blocks.from-agenda');
        Route::put('/{legislativeSession}/document/blocks/{block}', [ObDocumentController::class, 'updateBlock'])->name('document.blocks.update');
        Route::post('/{legislativeSession}/document/blocks/{block}/move-section', [ObDocumentController::class, 'moveBlockToSection'])->name('document.blocks.move-section');
        Route::delete('/{legislativeSession}/document/blocks/{block}', [ObDocumentController::class, 'destroyBlock'])->name('document.blocks.destroy');
        Route::get('/{legislativeSession}/attendance', [SessionAttendanceController::class, 'show'])->name('sessions.attendance');
        Route::put('/{legislativeSession}/attendance', [SessionAttendanceController::class, 'update'])->name('sessions.attendance.update');
        Route::get('/attendance/monthly', [SessionAttendanceController::class, 'monthlyReport'])->name('sessions.attendance.monthly');
        Route::get('/{legislativeSession}', [LegislativeSessionController::class, 'show'])->name('sessions.show');
        Route::get('/{legislativeSession}/edit', [LegislativeSessionController::class, 'edit'])->name('sessions.edit');
        Route::put('/{legislativeSession}', [LegislativeSessionController::class, 'update'])->name('sessions.update');
        Route::delete('/{legislativeSession}', [LegislativeSessionController::class, 'destroy'])->name('sessions.destroy');
    });

    Route::middleware('role:superadmin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/data-sync', [DataSyncController::class, 'index'])->name('data-sync.index');
        Route::post('/data-sync/resolutions', [DataSyncController::class, 'syncResolutions'])->name('data-sync.resolutions');
        Route::post('/data-sync/incoming', [DataSyncController::class, 'syncIncoming'])->name('data-sync.incoming');
        Route::post('/data-sync/sptrack-resolutions', [DataSyncController::class, 'syncSptrackResolutions'])->name('data-sync.sptrack-resolutions');
        Route::post('/data-sync/agenda', [DataSyncController::class, 'syncAgenda'])->name('data-sync.agenda');

        Route::get('/backups', [DatabaseBackupController::class, 'index'])->name('backups.index');
        Route::post('/backups/settings', [DatabaseBackupController::class, 'updateSettings'])->name('backups.settings');
        Route::post('/backups', [DatabaseBackupController::class, 'store'])->name('backups.store');
        Route::post('/backups/restore', [DatabaseBackupController::class, 'restore'])->name('backups.restore');
        Route::post('/backups/restore-upload', [DatabaseBackupController::class, 'restoreUpload'])->name('backups.restore-upload');
        Route::get('/backups/{filename}', [DatabaseBackupController::class, 'download'])
            ->where('filename', 'splis-\d{4}-\d{2}-\d{2}-\d{6}\.sql\.gz')
            ->name('backups.download');
    });

    Route::middleware('role:superadmin')->prefix('admin')->name('users.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('index');
        Route::get('/users/create', [UserController::class, 'create'])->name('create');
        Route::post('/users', [UserController::class, 'store'])->name('store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('destroy');
    });
});
