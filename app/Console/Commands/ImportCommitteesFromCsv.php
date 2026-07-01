<?php

namespace App\Console\Commands;

use App\Models\Committee;
use App\Services\CsvExportReader;
use Illuminate\Console\Command;

class ImportCommitteesFromCsv extends Command
{
    protected $signature = 'splis:import-committees
                            {--path= : Path to Committees.csv}
                            {--dry-run : Preview without writing}';

    protected $description = 'Import SP committees from oldsp/Committees.csv';

    public function handle(CsvExportReader $csv): int
    {
        $path = $this->option('path') ?: config('committees.csv_path');

        if (! is_file($path)) {
            $this->error("CSV file not found: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $imported = 0;
        $updated = 0;

        foreach ($csv->rows($path) as $row) {
            $name = trim((string) ($row['Committees'] ?? $row['Committees '] ?? ''));
            if ($name === '') {
                continue;
            }

            $sortOrder = (int) ($row['No.'] ?? 0);
            $payload = [
                'sort_order' => $sortOrder > 0 ? $sortOrder : $imported + $updated + 1,
                'name' => $name,
                'chair' => $row['Chair'] ?? null,
                'email' => $row['email'] ?? null,
                'vice_chair' => $row['Vice Chair'] ?? null,
                'members' => $row['Members'] ?? null,
                'secretary' => $row['Committee Secretary'] ?? null,
                'is_active' => true,
            ];

            if ($dryRun) {
                $this->line(sprintf('#%d %s', $payload['sort_order'], $payload['name']));
                $imported++;

                continue;
            }

            $committee = Committee::query()->where('name', $name)->first();

            if ($committee) {
                $committee->update($payload);
                $updated++;
            } else {
                Committee::create($payload);
                $imported++;
            }
        }

        if ($dryRun) {
            $this->info("Dry run: {$imported} committee row(s) parsed.");
        } else {
            $this->info("Imported {$imported} new committee(s), updated {$updated}.");
        }

        return self::SUCCESS;
    }
}
