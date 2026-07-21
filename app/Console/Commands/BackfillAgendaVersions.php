<?php

namespace App\Console\Commands;

use App\Services\AgendaVersionService;
use Illuminate\Console\Command;

class BackfillAgendaVersions extends Command
{
    protected $signature = 'agenda:backfill-versions';

    protected $description = 'Create initial version snapshots for agenda items missing version history';

    public function handle(AgendaVersionService $versions): int
    {
        $created = $versions->backfillMissingInitialVersions();

        $this->info("Created {$created} initial agenda version(s).");

        return self::SUCCESS;
    }
}
