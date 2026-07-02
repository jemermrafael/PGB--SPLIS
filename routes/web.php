<?php

use App\Http\Controllers\BoardMemberController;
use App\Http\Controllers\CommitteeController;
use App\Http\Controllers\CommitteeTermController;
use App\Http\Controllers\LegislativeSessionController;
use App\Http\Controllers\ObAgendaPoolController;
use App\Http\Controllers\ObDocumentController;
use App\Http\Controllers\AgendaDeadlinePreviewController;
use App\Http\Controllers\AgendaItemController;
use App\Http\Controllers\AgendaSearchController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardSearchController;
use App\Http\Controllers\IncomingDocumentController;
use App\Http\Controllers\IncomingKeywordController;
use App\Http\Controllers\IncomingSearchController;
use App\Http\Controllers\ResolutionController;
use App\Http\Controllers\ResolutionPdfController;
use App\Http\Controllers\ResolutionSearchController;
use App\Http\Controllers\UserController;
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
    Route::get('/dashboard/documents/search', DashboardSearchController::class)->name('dashboard.documents.search');

    Route::get('/resolutions', [ResolutionController::class, 'index'])->name('resolutions.index');
    Route::get('/resolutions/search', ResolutionSearchController::class)->name('resolutions.search');
    Route::get('/resolutions/create', [ResolutionController::class, 'create'])->name('resolutions.create');
    Route::post('/resolutions', [ResolutionController::class, 'store'])->name('resolutions.store');
    Route::get('/resolutions/legacy/{id}', [ResolutionController::class, 'showLegacy'])->name('resolutions.show-legacy');
    Route::get('/resolutions/pdf/{series}/{resolutionNo}', ResolutionPdfController::class)
        ->where('resolutionNo', '.*')
        ->name('resolutions.pdf');
    Route::get('/resolutions/{resolution}', [ResolutionController::class, 'show'])->name('resolutions.show');
    Route::get('/resolutions/{resolution}/edit', [ResolutionController::class, 'edit'])->name('resolutions.edit');
    Route::put('/resolutions/{resolution}', [ResolutionController::class, 'update'])->name('resolutions.update');
    Route::delete('/resolutions/{resolution}', [ResolutionController::class, 'destroy'])->name('resolutions.destroy');

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

    Route::get('/committees', [CommitteeController::class, 'index'])->name('committees.index');
    Route::get('/committees/create', [CommitteeController::class, 'create'])->name('committees.create');
    Route::post('/committees', [CommitteeController::class, 'store'])->name('committees.store');
    Route::get('/committees/{committee}', [CommitteeController::class, 'show'])->name('committees.show');
    Route::get('/committees/{committee}/edit', [CommitteeController::class, 'edit'])->name('committees.edit');
    Route::put('/committees/{committee}', [CommitteeController::class, 'update'])->name('committees.update');
    Route::delete('/committees/{committee}', [CommitteeController::class, 'destroy'])->name('committees.destroy');

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
        Route::delete('/{legislativeSession}/document/blocks/{block}', [ObDocumentController::class, 'destroyBlock'])->name('document.blocks.destroy');
        Route::get('/{legislativeSession}', [LegislativeSessionController::class, 'show'])->name('sessions.show');
        Route::get('/{legislativeSession}/edit', [LegislativeSessionController::class, 'edit'])->name('sessions.edit');
        Route::put('/{legislativeSession}', [LegislativeSessionController::class, 'update'])->name('sessions.update');
        Route::delete('/{legislativeSession}', [LegislativeSessionController::class, 'destroy'])->name('sessions.destroy');
    });

    Route::redirect('/admin/sptrack', '/incoming')->name('admin.sptrack.index');
    Route::redirect('/admin/sptrack/queue', '/incoming');

    Route::middleware('role:superadmin')->prefix('admin')->name('users.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('index');
        Route::get('/users/create', [UserController::class, 'create'])->name('create');
        Route::post('/users', [UserController::class, 'store'])->name('store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('destroy');
    });
});
