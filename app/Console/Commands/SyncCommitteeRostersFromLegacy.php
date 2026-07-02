<?php

namespace App\Console\Commands;

use App\Models\Committee;
use App\Models\CommitteeTerm;
use App\Services\CommitteeRosterService;
use Illuminate\Console\Command;

class SyncCommitteeRostersFromLegacy extends Command
{
    protected $signature = 'splis:sync-committee-rosters {--dry-run : Preview without writing}';

    protected $description = 'Create board members and term rosters from legacy committee text fields';

    public function handle(CommitteeRosterService $rosterService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $term = CommitteeTerm::currentOrCreate();
        $count = 0;

        Committee::query()->ordered()->each(function (Committee $committee) use ($rosterService, $term, $dryRun, &$count): void {
            if ($committee->memberships()->where('committee_term_id', $term->id)->exists()) {
                return;
            }

            $roster = [
                'chair_id' => $this->personId($committee->chair, $rosterService, $dryRun),
                'vice_chair_id' => $this->personId($committee->vice_chair, $rosterService, $dryRun),
                'secretary' => trim((string) $committee->secretary) ?: null,
                'member_ids' => collect($committee->memberDisplayNames())
                    ->map(fn (string $name) => $this->personId($name, $rosterService, $dryRun))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ];

            if ($dryRun) {
                $this->line($committee->name);
                $count++;

                return;
            }

            $rosterService->saveRoster($committee, $term, $roster);
            $count++;
        });

        $this->info($dryRun
            ? "Dry run: {$count} committee roster(s) would be synced."
            : "Synced {$count} committee roster(s) for term \"{$term->label}\".");

        return self::SUCCESS;
    }

    protected function personId(?string $name, CommitteeRosterService $rosterService, bool $dryRun): ?int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        if ($dryRun) {
            return 1;
        }

        return $rosterService->findOrCreateBoardMemberByName($name)->id;
    }
}
