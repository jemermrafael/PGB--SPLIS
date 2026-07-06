<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupController extends Controller
{
    public function index(DatabaseBackupService $backups): View
    {
        return view('admin.backups.index', [
            'backups' => $backups->list(),
            'retentionDays' => config('backup.retention_days', 14),
            'scheduleTime' => config('backup.schedule_time', '02:00'),
            'directory' => $backups->directory(),
        ]);
    }

    public function store(Request $request, DatabaseBackupService $backups): RedirectResponse
    {
        try {
            $file = $backups->create();
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLogger::log('backup.created', null, [
            'filename' => $file['filename'],
            'size' => $file['size'],
            'scheduled' => false,
        ]);

        return back()->with('status', "Backup created: {$file['filename']}");
    }

    public function download(string $filename, DatabaseBackupService $backups): StreamedResponse|RedirectResponse
    {
        try {
            $response = $backups->downloadResponse($filename);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLogger::log('backup.downloaded', null, [
            'filename' => $filename,
        ]);

        return $response;
    }
}
