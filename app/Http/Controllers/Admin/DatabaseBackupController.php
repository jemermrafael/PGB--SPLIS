<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupSettings;
use App\Services\DatabaseBackupService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupController extends Controller
{
    public function index(DatabaseBackupService $backups, BackupSettings $settings): View
    {
        return view('admin.backups.index', [
            'backups' => $backups->list(),
            'retentionDays' => $settings->retentionDays(),
            'scheduleTime' => $settings->scheduleTime(),
            'directory' => $backups->directory(),
        ]);
    }

    public function updateSettings(Request $request, BackupSettings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'schedule_time' => ['required', 'date_format:H:i'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $settings->update([
            'schedule_time' => $validated['schedule_time'],
            'retention_days' => (int) $validated['retention_days'],
        ]);

        ActivityLogger::log('backup.settings_updated', null, $validated);

        return back()->with('status', 'Backup settings saved.');
    }

    public function store(DatabaseBackupService $backups): RedirectResponse
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

    public function restore(Request $request, DatabaseBackupService $backups): RedirectResponse
    {
        $request->validate([
            'filename' => ['required', 'string'],
            'confirm_restore' => ['required', 'in:RESTORE'],
        ]);

        try {
            $backups->restore($request->string('filename')->toString());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLogger::log('backup.restored', null, [
            'filename' => $request->string('filename')->toString(),
            'source' => 'server',
        ]);

        return back()->with('status', 'Database restored from '.$request->string('filename'));
    }

    public function restoreUpload(Request $request, DatabaseBackupService $backups): RedirectResponse
    {
        $request->validate([
            'backup_file' => ['required', 'file', 'max:512000'],
            'confirm_restore' => ['required', 'in:RESTORE'],
        ]);

        try {
            $backups->restoreUpload($request->file('backup_file'));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLogger::log('backup.restored', null, [
            'filename' => $request->file('backup_file')?->getClientOriginalName(),
            'source' => 'upload',
        ]);

        return back()->with('status', 'Database restored from uploaded backup.');
    }
}
