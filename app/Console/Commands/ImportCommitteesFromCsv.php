<?php

namespace App\Console\Commands;

use App\Models\Committee;
use App\Models\CommitteeTerm;
use App\Services\CommitteeRosterService;
use App\Services\CsvExportReader;
use Illuminate\Console\Command;

class ImportCommitteesFromCsv extends Command
{
    protected $signature = 'splis:import-committees
                            {--path= : Path to Committees.csv}
                            {--dry-run : Preview without writing}';

    protected $description = 'Import SP committees from oldsp/Committees.csv';

    public function handle(CsvExportReader $csv, CommitteeRosterService $rosterService): int
    {
        $path = $this->option('path') ?: config('committees.csv_path');

        if (! is_file($path)) {
            $this->error("CSV file not found: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $imported = 0;
        $updated = 0;
        $term = CommitteeTerm::currentOrCreate();

        foreach ($csv->rows($path) as $row) {
            $name = trim((string) ($row['Committees'] ?? $row['Committees '] ?? ''));
            if ($name === '') {
                continue;
            }

            $sortOrder = (int) ($row['No.'] ?? 0);
            $payload = [
                'sort_order' => $sortOrder > 0 ? $sortOrder : $imported + $updated + 1,
                'name' => $name,
                'email' => $row['email'] ?? null,
                'is_active' => true,
            ];

            $roster = [
                'chair_id' => $this->resolvePersonId($row['Chair'] ?? null, $dryRun),
                'vice_chair_id' => $this->resolvePersonId($row['Vice Chair'] ?? null, $dryRun),
                'secretary' => trim((string) ($row['Committee Secretary'] ?? '')) ?: null,
                'member_ids' => $this->resolveMemberIds($row['Members'] ?? null, $dryRun),
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
                $committee = Committee::create($payload);
                $imported++;
            }

            $rosterService->saveRoster($committee, $term, $roster);
        }

        if ($dryRun) {
            $this->info("Dry run: {$imported} committee row(s) parsed.");
        } else {
            $this->info("Imported {$imported} new committee(s), updated {$updated}.");
        }

        return self::SUCCESS;
    }

    protected function resolvePersonId(?string $name, bool $dryRun): ?int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        if ($dryRun) {
            return 1;
        }

        return app(CommitteeRosterService::class)->findOrCreateBoardMemberByName($name)->id;
    }

    /**
     * @return list<int>
     */
    protected function resolveMemberIds(?string $raw, bool $dryRun): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [];
        }

        $names = collect(preg_split('/\r\n|\r|\n|,/', $raw) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();

        if ($dryRun) {
            return $names->take(3)->map(fn () => 1)->all();
        }

        $service = app(CommitteeRosterService::class);

        return $names
            ->map(fn (string $name) => $service->findOrCreateBoardMemberByName($name)->id)
            ->unique()
            ->values()
            ->all();
    }
}
