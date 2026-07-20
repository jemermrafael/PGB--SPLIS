<?php

namespace App\Console\Commands;

use App\Models\AgendaItem;
use App\Services\AgendaPdfMirrorService;
use App\Services\AgendaPdfService;
use Illuminate\Console\Command;

class MirrorAgendaPdfs extends Command
{
    protected $signature = 'agenda:mirror-pdfs
                            {--limit=5 : Max agenda items to process (0 = all)}
                            {--id= : Mirror a single agenda item by ID}
                            {--overwrite : Re-download even when a local file already exists}
                            {--dry-run : Count matching rows without downloading}';

    protected $description = 'Download agenda document URLs from Drive into private storage';

    public function handle(AgendaPdfMirrorService $mirror, AgendaPdfService $pdfs): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $id = $this->option('id') !== null && $this->option('id') !== ''
            ? (int) $this->option('id')
            : null;
        $overwrite = (bool) $this->option('overwrite');
        $dryRun = (bool) $this->option('dry-run');

        $this->info(($dryRun ? '[DRY RUN] ' : '').'Mirroring agenda documents from URLs…');

        $query = AgendaItem::query()->orderBy('id');

        if ($id !== null) {
            $query->whereKey($id);
        } elseif ($limit > 0) {
            $query->limit($limit);
        }

        $mirrored = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($query->cursor() as $agenda) {
            /** @var AgendaItem $agenda */
            if ($dryRun) {
                $skipped += count($pdfs->missingMirrorSlots($agenda));

                continue;
            }

            $result = $mirror->mirrorAllFor($agenda, overwrite: $overwrite);

            $mirrored += $result['mirrored'];
            $skipped += $result['skipped'];
            $failed += $result['failed'];

            foreach ($result['messages'] as $message) {
                if ($result['failed'] > 0) {
                    $errors[] = 'Agenda #'.$agenda->id.': '.$message;
                }
            }
        }

        $this->table(['Metric', 'Count'], [
            ['Mirrored', $mirrored],
            ['Skipped', $skipped],
            ['Failed', $failed],
        ]);

        foreach (array_slice($errors, 0, 20) as $error) {
            $this->warn($error);
        }

        return $failed > 0 && $mirrored === 0 ? self::FAILURE : self::SUCCESS;
    }
}
