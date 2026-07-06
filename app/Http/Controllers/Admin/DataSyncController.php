<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\AgendaCsvImporter;
use App\Services\CsvExportReader;
use App\Services\DataSyncCsvStorage;
use App\Services\IncomingDocumentImporter;
use App\Services\ResolutionCsvImporter;
use App\Services\SptrackReader;
use App\Services\SptrackResolutionSyncService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class DataSyncController extends Controller
{
    public function index(CsvExportReader $csv, SptrackReader $sptrack): View
    {
        $csvDirectory = $csv->resolveDirectory(null);
        $spFile = $csv->findNewest($csvDirectory, 'SP_');

        $agendaCsvPath = config('agenda.csv_path');
        $agendaLinksPath = config('agenda.csv_links_path');

        $recentLogs = ActivityLog::query()
            ->whereIn('action', [
                'data_sync.resolutions_csv',
                'data_sync.sptrack_incoming',
                'data_sync.sptrack_resolutions',
                'data_sync.agenda_csv',
            ])
            ->with('user')
            ->latest('created_at')
            ->limit(10)
            ->get();

        return view('admin.data-sync.index', [
            'csvDirectory' => $csvDirectory,
            'spCsvFile' => $spFile ? basename($spFile) : null,
            'sptrackSource' => $sptrack->canUseDatabase() ? 'MySQL sptrack' : 'CSV fallback',
            'sptrackCsvExists' => $sptrack->defaultCsvPathExists(),
            'agendaCsvPath' => $agendaCsvPath,
            'agendaCsvExists' => is_file($agendaCsvPath),
            'agendaLinksPath' => $agendaLinksPath,
            'agendaLinksExists' => is_file($agendaLinksPath),
            'recentLogs' => $recentLogs,
        ]);
    }

    public function syncResolutions(
        Request $request,
        ResolutionCsvImporter $importer,
        DataSyncCsvStorage $uploads,
    ): RedirectResponse {
        $request->validate([
            'sp_csv' => ['nullable', 'file', 'mimes:csv,txt', 'max:51200'],
            'include_lookups' => ['nullable', 'boolean'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $dryRun = $request->boolean('dry_run');
        $uploadedPath = $this->storeUpload($request->file('sp_csv'), $uploads);

        try {
            $stats = $importer->sync(
                includeLookups: $request->boolean('include_lookups'),
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
                'uploaded' => $uploadedPath !== null,
                'sp_file' => basename((string) $stats['sp_file']),
                'stats' => $stats,
            ]);
        }

        $prefix = $dryRun ? '[Dry run] ' : '';
        $source = $uploadedPath !== null ? 'uploaded file' : 'server export';

        return back()->with('status', sprintf(
            '%sFinal resolutions synced from %s (%s) — %d processed (%d created, %d updated, %d skipped).',
            $prefix,
            basename((string) $stats['sp_file']),
            $source,
            $stats['processed'],
            $stats['created'],
            $stats['updated'],
            $stats['skipped'],
        ));
    }

    public function syncIncoming(
        Request $request,
        IncomingDocumentImporter $importer,
        SptrackReader $reader,
        DataSyncCsvStorage $uploads,
    ): RedirectResponse {
        $request->validate([
            'sptrack_csv' => ['nullable', 'file', 'mimes:csv,txt', 'max:51200'],
            'source' => ['nullable', 'in:auto,database,csv'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $dryRun = $request->boolean('dry_run');
        $uploadedPath = $this->storeUpload($request->file('sptrack_csv'), $uploads);
        $source = $uploadedPath !== null
            ? 'csv'
            : $this->resolveSptrackSource($request->input('source', 'auto'), $reader);

        try {
            $stats = $importer->syncFromSptrack(
                csvPath: $uploadedPath,
                source: $source,
                dryRun: $dryRun,
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        } finally {
            $uploads->delete($uploadedPath);
        }

        if (! $dryRun) {
            ActivityLogger::log('data_sync.sptrack_incoming', null, [
                'source' => $source,
                'uploaded' => $uploadedPath !== null,
                'stats' => $stats,
            ]);
        }

        $prefix = $dryRun ? '[Dry run] ' : '';

        return back()->with('status', sprintf(
            '%sSptrack incoming synced — %d rows (%d created, %d updated).',
            $prefix,
            $stats['total'],
            $stats['created'],
            $stats['updated'],
        ));
    }

    public function syncAgenda(
        Request $request,
        AgendaCsvImporter $importer,
        DataSyncCsvStorage $uploads,
    ): RedirectResponse {
        $request->validate([
            'agenda_csv' => ['nullable', 'file', 'mimes:csv,txt', 'max:51200'],
            'agenda_links_csv' => ['nullable', 'file', 'mimes:csv,txt', 'max:51200'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $dryRun = $request->boolean('dry_run');
        $uploadedAgenda = $this->storeUpload($request->file('agenda_csv'), $uploads);
        $uploadedLinks = $this->storeUpload($request->file('agenda_links_csv'), $uploads);

        try {
            $stats = $importer->sync(
                csvPath: $uploadedAgenda,
                linksPath: $uploadedLinks,
                dryRun: $dryRun,
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        } finally {
            $uploads->delete($uploadedAgenda);
            $uploads->delete($uploadedLinks);
        }

        if (! $dryRun) {
            ActivityLogger::log('data_sync.agenda_csv', null, [
                'uploaded_agenda' => $uploadedAgenda !== null,
                'uploaded_links' => $uploadedLinks !== null,
                'agenda_file' => basename((string) $stats['agenda_file']),
                'links_file' => $stats['links_file'] ? basename($stats['links_file']) : null,
                'stats' => $stats,
            ]);
        }

        $prefix = $dryRun ? '[Dry run] ' : '';
        $source = $uploadedAgenda !== null ? 'uploaded file' : 'server export';
        $linksNote = $stats['links_file']
            ? ' with PDF links from '.basename($stats['links_file'])
            : '';

        return back()->with('status', sprintf(
            '%sAgenda synced from %s (%s)%s — %d rows (%d created, %d updated).',
            $prefix,
            basename((string) $stats['agenda_file']),
            $source,
            $linksNote,
            $stats['total'],
            $stats['imported'],
            $stats['updated'],
        ));
    }

    public function syncSptrackResolutions(
        Request $request,
        SptrackResolutionSyncService $sync,
        SptrackReader $reader,
        DataSyncCsvStorage $uploads,
    ): RedirectResponse {
        $request->validate([
            'sptrack_csv' => ['nullable', 'file', 'mimes:csv,txt', 'max:51200'],
            'source' => ['nullable', 'in:auto,database,csv'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $dryRun = $request->boolean('dry_run');
        $uploadedPath = $this->storeUpload($request->file('sptrack_csv'), $uploads);
        $source = $uploadedPath !== null
            ? 'csv'
            : $this->resolveSptrackSource($request->input('source', 'auto'), $reader);

        try {
            $stats = $sync->sync(
                csvPath: $uploadedPath,
                source: $source,
                dryRun: $dryRun,
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        } finally {
            $uploads->delete($uploadedPath);
        }

        if (! $dryRun) {
            ActivityLogger::log('data_sync.sptrack_resolutions', null, [
                'source' => $source,
                'uploaded' => $uploadedPath !== null,
                'stats' => $stats,
            ]);
        }

        $prefix = $dryRun ? '[Dry run] ' : '';

        return back()->with('status', sprintf(
            '%sLinked resolutions updated from sptrack — %d rows (%d updated, %d skipped with no linked resolution).',
            $prefix,
            $stats['total'],
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

    protected function resolveSptrackSource(string $requested, SptrackReader $reader): string
    {
        if (in_array($requested, ['database', 'csv'], true)) {
            return $requested;
        }

        if ($reader->defaultCsvPathExists()) {
            return 'csv';
        }

        return $reader->canUseDatabase() ? 'database' : 'csv';
    }
}
