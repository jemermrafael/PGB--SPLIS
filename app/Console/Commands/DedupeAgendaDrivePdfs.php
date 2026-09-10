<?php

namespace App\Console\Commands;

use App\Services\AgendaDrivePdfShareService;
use App\Support\AgendaPdfSlot;
use Illuminate\Console\Command;

class DedupeAgendaDrivePdfs extends Command
{
    protected $signature = 'agenda:dedupe-shared-drive-pdfs
                            {--slot= : Limit to one slot (request, committee_report, reso_ord_ao, journal, minutes)}
                            {--dry-run : Report only; do not relink or delete files}
                            {--report : List duplicate Drive URL groups without changing files}';

    protected $description = 'Share one local PDF among agendas with the same Google Drive/file URL, and purge duplicate downloads';

    public function handle(AgendaDrivePdfShareService $shares): int
    {
        $slot = $this->option('slot');

        if ($slot !== null && $slot !== '' && ! AgendaPdfSlot::isValid($slot)) {
            $this->error('Invalid --slot. Use: '.implode(', ', AgendaPdfSlot::all()));

            return self::FAILURE;
        }

        $slotFilter = filled($slot) ? (string) $slot : null;

        if ($this->option('report') || $this->option('dry-run')) {
            $groups = $shares->duplicateGroups($slotFilter);

            if ($groups === []) {
                $this->info('No shared Drive/file URL groups found across agendas.');

                return self::SUCCESS;
            }

            $this->info(sprintf('Found %d shared Drive/file URL group(s):', count($groups)));
            $this->newLine();

            foreach ($groups as $group) {
                $this->line(sprintf(
                    '• %s · %d agendas · %d local path(s) · %s',
                    AgendaPdfSlot::config($group['slot'])['label'],
                    $group['agenda_count'],
                    count($group['distinct_paths']),
                    $group['sample_url'],
                ));
                $this->line('  agenda ids: '.implode(', ', $group['agenda_ids']));
                if ($group['distinct_paths'] !== []) {
                    $this->line('  paths: '.implode(' | ', $group['distinct_paths']));
                }
            }

            if ($this->option('report') && ! $this->option('dry-run')) {
                return self::SUCCESS;
            }
        }

        $result = $shares->dedupe(
            dryRun: (bool) $this->option('dry-run'),
            slotFilter: $slotFilter,
        );

        $prefix = $result['dry_run'] ? '[DRY RUN] ' : '';

        $this->newLine();
        $this->info(sprintf(
            '%s%d group(s), %d agenda(s) relinked, %d duplicate file(s) purged, %d shared file(s) kept.',
            $prefix,
            $result['groups'],
            $result['linked'],
            $result['purged'],
            $result['kept_paths'],
        ));

        foreach ($result['details'] as $line) {
            $this->line('  '.$line);
        }

        return self::SUCCESS;
    }
}
