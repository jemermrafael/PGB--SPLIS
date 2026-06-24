<?php

namespace App\Console\Commands;

use App\Models\Resolution;
use App\Services\PdfAttachmentService;
use Illuminate\Console\Command;

class VerifyPdfs extends Command
{
    protected $signature = 'resolutions:verify-pdfs {--limit=0 : Limit rows checked (0 = all)}';

    protected $description = 'Report how many resolutions have a matching PDF on disk';

    public function handle(PdfAttachmentService $pdf): int
    {
        $query = Resolution::query()->orderBy('id');
        $limit = (int) $this->option('limit');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = 0;
        $withPdf = 0;
        $withPath = 0;

        $bar = $this->output->createProgressBar($limit > 0 ? $limit : Resolution::count());
        $bar->start();

        $query->chunk(500, function ($rows) use ($pdf, &$total, &$withPdf, &$withPath, $bar) {
            foreach ($rows as $row) {
                $total++;

                if ($row->pdf_path) {
                    $withPath++;
                }

                if ($pdf->existsFor($row)) {
                    $withPdf++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $pct = $total > 0 ? round(($withPdf / $total) * 100, 1) : 0;
        $this->table(['Metric', 'Value'], [
            ['Checked', $total],
            ['With PDF on disk', $withPdf],
            ['With pdf_path in DB', $withPath],
            ['Without PDF', $total - $withPdf],
            ['Match rate', $pct.'%'],
        ]);

        return self::SUCCESS;
    }
}
