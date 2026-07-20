<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\AgendaCsvImporter;
use App\Services\DataSyncCsvStorage;
use App\Services\ResolutionCsvImporter;
use App\Services\ResolutionPdfLinkService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class DataSyncController extends Controller
{
    public function index(): View
    {
        $recentLogs = ActivityLog::query()
            ->whereIn('action', [
                'data_sync.resolutions_csv',
                'data_sync.sptrack_incoming',
                'data_sync.sptrack_resolutions',
                'data_sync.agenda_csv',
                'data_sync.link_pdfs',
            ])
            ->with('user')
            ->latest('created_at')
            ->limit(10)
            ->get();

        return view('admin.data-sync.index', [
            'recentLogs' => $recentLogs,
        ]);
    }

    public function syncResolutions(
        Request $request,
        ResolutionCsvImporter $importer,
        DataSyncCsvStorage $uploads,
    ): RedirectResponse {
        $request->validate([
            'sp_csv' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $dryRun = $request->boolean('dry_run');
        $uploadedPath = $this->storeUpload($request->file('sp_csv'), $uploads);

        try {
            $stats = $importer->sync(
                includeLookups: false,
                dryRun: $dryRun,
                spFilePath: $uploadedPath,
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        } finally {
            $uploads->delete($uploadedPath);
        }

        if (! $dryRun) {
            ActivityLogger::log('data_sync.resolutions_csv', null, [
                'dry_run' => false,
                'uploaded' => true,
                'sp_file' => basename((string) $stats['sp_file']),
                'stats' => $stats,
            ]);
        }

        $prefix = $dryRun ? '[Dry run] ' : '';

        return back()->with('status', sprintf(
            '%sFinal resolutions synced from %s (uploaded file) — %d processed (%d created, %d updated, %d skipped).%s',
            $prefix,
            basename((string) $stats['sp_file']),
            $stats['processed'],
            $stats['created'],
            $stats['updated'],
            $stats['skipped'],
            $this->resolutionDuplicateSummary($stats),
        ));
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    protected function resolutionDuplicateSummary(array $stats): string
    {
        $parts = [];

        if (($stats['csv_duplicate_legacy'] ?? 0) > 0) {
            $parts[] = sprintf('%d duplicate legacy ID(s) in CSV', $stats['csv_duplicate_legacy']);
        }
        if (($stats['csv_duplicate_number_series'] ?? 0) > 0) {
            $parts[] = sprintf('%d duplicate series/number pair(s) in CSV', $stats['csv_duplicate_number_series']);
        }
        if (($stats['conflicting_active_number'] ?? 0) > 0) {
            $parts[] = sprintf('%d row(s) conflict with a different active resolution number', $stats['conflicting_active_number']);
        }

        return $parts === [] ? '' : ' Duplicates: '.implode('; ', $parts).'.';
    }

    public function syncAgenda(
        Request $request,
        AgendaCsvImporter $importer,
        DataSyncCsvStorage $uploads,
    ): RedirectResponse {
        $request->validate([
            'agenda_csv' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $dryRun = $request->boolean('dry_run');
        $uploadedAgenda = $this->storeUpload($request->file('agenda_csv'), $uploads);

        try {
            $stats = $importer->sync(
                csvPath: $uploadedAgenda,
                linksPath: null,
                dryRun: $dryRun,
                allowConfiguredLinksFallback: false,
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        } finally {
            $uploads->delete($uploadedAgenda);
        }

        if (! $dryRun) {
            ActivityLogger::log('data_sync.agenda_csv', null, [
                'uploaded_agenda' => true,
                'uploaded_links' => false,
                'agenda_file' => basename((string) $stats['agenda_file']),
                'links_file' => $stats['links_file'] ? basename($stats['links_file']) : null,
                'stats' => $stats,
            ]);
        }

        $prefix = $dryRun ? '[Dry run] ' : '';

        return back()->with('status', sprintf(
            '%sAgenda synced from %s (uploaded file) — %d rows (%d created, %d updated%s).',
            $prefix,
            basename((string) $stats['agenda_file']),
            $stats['total'],
            $stats['imported'],
            $stats['updated'],
            ($stats['urgent'] ?? 0) > 0
                ? sprintf(', %d urgent without tracking no.', $stats['urgent'])
                : '',
        ));
    }

    public function linkPdfs(
        Request $request,
        ResolutionPdfLinkService $linker,
    ): RedirectResponse {
        $request->validate([
            'only_missing' => ['nullable', 'boolean'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $dryRun = $request->boolean('dry_run');
        $onlyMissing = $request->boolean('only_missing');

        try {
            $stats = $linker->link(
                onlyMissing: $onlyMissing,
                dryRun: $dryRun,
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if (! $dryRun) {
            ActivityLogger::log('data_sync.link_pdfs', null, [
                'only_missing' => $onlyMissing,
                'stats' => $stats,
            ]);
        }

        $prefix = $dryRun ? '[Dry run] ' : '';

        return back()->with('status', sprintf(
            '%sResolution pdf_path backfilled (format resolutions/{series}/{resolution_no}.pdf) — %d updated, %d skipped.',
            $prefix,
            $stats['updated'],
            $stats['skipped'],
        ));
    }

    protected function storeUpload(?UploadedFile $file, DataSyncCsvStorage $uploads): ?string
    {
        if ($file === null || ! $file->isValid()) {
            return null;
        }

        return $uploads->store($file);
    }
}
