<?php

namespace Tests\Unit;

use App\Models\AgendaItem;
use App\Services\AgendaCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaCsvImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_urgent_rows_without_tracking_number(): void
    {
        $csv = $this->writeCsv([
            [' ', 'Request PDF', 'Date Received', 'Sender', 'Title', 'Status', 'Prescribed Dates'],
            ['', '', '2026-03-10', 'Mariveles', 'Urgent municipal request sample', 'Pending', '30'],
        ]);

        $stats = app(AgendaCsvImporter::class)->sync($csv, linksPath: $this->missingLinksPath(), dryRun: false);

        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['imported']);
        $this->assertSame(1, $stats['urgent']);

        $agenda = AgendaItem::query()->first();
        $this->assertNotNull($agenda);
        $this->assertNull($agenda->tracking_no);
        $this->assertTrue($agenda->is_urgent_request);
        $this->assertSame('Mariveles', $agenda->sender);
    }

    public function test_it_assigns_tracking_number_to_existing_urgent_row(): void
    {
        AgendaItem::query()->create([
            'tracking_no' => null,
            'is_urgent_request' => true,
            'sender' => 'Orion',
            'title' => 'Sample urgent request',
            'date_received' => '2026-04-01',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 30,
        ]);

        $csv = $this->writeCsv([
            [' ', 'Request PDF', 'Date Received', 'Sender', 'Title', 'Status', 'Prescribed Dates'],
            ['225', '', '2026-04-01', 'Orion', 'Sample urgent request', 'Pending', '30'],
        ]);

        $stats = app(AgendaCsvImporter::class)->sync($csv, linksPath: $this->missingLinksPath(), dryRun: false);

        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['updated']);
        $this->assertSame(0, $stats['imported']);
        $this->assertSame(1, AgendaItem::query()->count());

        $agenda = AgendaItem::query()->first();
        $this->assertSame('225', $agenda->tracking_no);
        $this->assertFalse($agenda->is_urgent_request);
    }

    public function test_it_updates_urgent_row_on_repeat_import_without_tracking_number(): void
    {
        AgendaItem::query()->create([
            'tracking_no' => null,
            'is_urgent_request' => true,
            'sender' => 'Hermosa',
            'title' => 'Pending numbering',
            'date_received' => '2026-05-01',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 30,
            'remarks' => 'Old note',
        ]);

        $csv = $this->writeCsv([
            [' ', 'Request PDF', 'Date Received', 'Sender', 'Title', 'Status', 'Prescribed Dates', 'Status/ Remarks'],
            ['', '', '2026-05-01', 'Hermosa', 'Pending numbering', 'Pending', '30', 'Updated note'],
        ]);

        $stats = app(AgendaCsvImporter::class)->sync($csv, linksPath: $this->missingLinksPath(), dryRun: false);

        $this->assertSame(1, $stats['updated']);
        $this->assertSame(1, AgendaItem::query()->count());
        $this->assertSame('Updated note', AgendaItem::query()->value('remarks'));
    }

    public function test_it_reads_tracking_number_from_column_a_when_headers_are_duplicate_blanks(): void
    {
        $csv = $this->writeCsv([
            [' ', '', 'Date Received', 'Sender', 'Title', 'Status', 'Prescribed Dates'],
            ['122', 'https://drive.google.com/file/d/example/view', '2026-03-10', 'Mariveles', 'Sample request', 'Pending', '30'],
        ]);

        $stats = app(AgendaCsvImporter::class)->sync($csv, linksPath: $this->missingLinksPath(), dryRun: false);

        $this->assertSame(1, $stats['total']);
        $this->assertSame(0, $stats['urgent']);
        $this->assertSame(1, $stats['imported']);

        $agenda = AgendaItem::query()->first();
        $this->assertSame('122', $agenda->tracking_no);
        $this->assertFalse($agenda->is_urgent_request);
        $this->assertSame('https://drive.google.com/file/d/example/view', $agenda->request_pdf_url);
    }

    public function test_it_treats_dash_in_column_a_as_unnumbered_urgent(): void
    {
        $csv = $this->writeCsv([
            [' ', '', 'Date Received', 'Sender', 'Title', 'Status', 'Prescribed Dates'],
            ['-', 'https://drive.google.com/file/d/example/view', '2026-03-10', 'PGO', 'Urgent without number yet', 'Pending', '0'],
        ]);

        $stats = app(AgendaCsvImporter::class)->sync($csv, linksPath: $this->missingLinksPath(), dryRun: false);

        $this->assertSame(1, $stats['urgent']);
        $this->assertNull(AgendaItem::query()->value('tracking_no'));
        $this->assertTrue(AgendaItem::query()->value('is_urgent_request'));
    }

    public function test_it_preserves_existing_request_pdf_when_csv_url_is_blank(): void
    {
        AgendaItem::query()->create([
            'tracking_no' => '306',
            'sender' => 'Hermosa',
            'title' => 'Ordinance with existing PDF',
            'date_received' => '2026-07-08',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 30,
            'request_pdf_url' => 'https://drive.google.com/file/d/keep-me/view',
        ]);

        $csv = $this->writeCsv([
            [' ', '', 'Date Received', 'Sender', 'Title', 'Status', 'Prescribed Dates'],
            ['306', '', '2026-07-08', 'Hermosa', 'Ordinance with existing PDF', 'Pending', '30'],
        ]);

        app(AgendaCsvImporter::class)->sync($csv, linksPath: $this->missingLinksPath(), dryRun: false);

        $this->assertSame(
            'https://drive.google.com/file/d/keep-me/view',
            AgendaItem::query()->where('tracking_no', '306')->value('request_pdf_url'),
        );
    }

    /**
     * @param  list<list<string>>  $rows
     */
    protected function writeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'agenda-import-');
        $this->assertNotFalse($path);

        $handle = fopen($path, 'w');
        $this->assertNotFalse($handle);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }

    protected function missingLinksPath(): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'agenda-import-missing-links.csv';
    }
}
