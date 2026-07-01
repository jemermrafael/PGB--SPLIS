<?php

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
