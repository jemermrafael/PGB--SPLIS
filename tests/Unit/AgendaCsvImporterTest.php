<?php

namespace Tests\Unit;

use App\Models\AgendaItem;
use App\Models\AppropriationOrdinance;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Services\AgendaCsvImporter;
use App\Support\AgendaMeasureType;
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

    public function test_reimport_clears_wrong_resolution_link_and_relinks_ordinance(): void
    {
        $bogusResolution = Resolution::query()->create([
            'resolution_no' => 'Ord. No. 22',
            'resolution_title' => 'Should not stay linked',
            'series' => 2026,
            'status' => 'approved',
        ]);

        $ordinance = Ordinance::query()->create([
            'ordinance_no' => 22,
            'series_year' => 2026,
            'subject' => 'Rice subsidy ordinance',
        ]);

        $agenda = AgendaItem::query()->create([
            'tracking_no' => '269',
            'sender' => 'PGO',
            'title' => 'Rice subsidy',
            'date_received' => '2026-01-15',
            'status' => AgendaItem::STATUS_DONE,
            'prescribed_days' => 30,
            'reso_ord_ao_no' => 'Ord. No. 22',
            'reso_ord_ao_series' => 2026,
            'reso_ord_ao_type' => AgendaMeasureType::RESOLUTION,
            'resolution_id' => $bogusResolution->id,
            'published_at' => now(),
        ]);

        $csv = $this->writeCsv([
            [
                ' ',
                'Request PDF',
                'Date Received',
                'Sender',
                'Title',
                'Status',
                'Prescribed Dates',
                'Date passed',
                'Reso./Ord./AO No.',
                'Resolution Title',
            ],
            [
                '269',
                '',
                '2026-01-15',
                'PGO',
                'Rice subsidy',
                'Done',
                '30',
                '2026-02-01',
                'Ord. No. 22',
                'An ordinance consolidating the rice subsidy program',
            ],
        ]);

        app(AgendaCsvImporter::class)->sync($csv, linksPath: $this->missingLinksPath(), dryRun: false);

        $agenda->refresh();

        $this->assertNull($agenda->resolution_id);
        $this->assertSame($ordinance->id, $agenda->ordinance_id);
        $this->assertSame(AgendaMeasureType::ORDINANCE, $agenda->reso_ord_ao_type);
        $this->assertNotNull($agenda->published_at);
        $this->assertSame(AgendaItem::OUTPUT_CONNECTION_LINKED, $agenda->output_connection_type);
        $this->assertSame(1, $agenda->versions()->count());
        $this->assertSame('imported', $agenda->versions()->first()->change_reason);
    }

    public function test_reimport_relinks_matching_resolution_for_done_agenda(): void
    {
        $staleAppropriation = AppropriationOrdinance::query()->create([
            'ordinance_no' => 301,
            'series_year' => 2026,
            'subject' => 'Stale appropriation connection',
        ]);
        $resolution = Resolution::query()->create([
            'resolution_no' => '2026-301',
            'resolution_title' => 'Matching resolution',
            'series' => 2026,
            'status' => 'approved',
        ]);

        AgendaItem::query()->create([
            'tracking_no' => '301',
            'sender' => 'Balanga',
            'title' => 'Request title',
            'date_received' => '2026-03-01',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 30,
            'reso_ord_ao_no' => '301',
            'reso_ord_ao_series' => 2026,
            'reso_ord_ao_type' => AgendaMeasureType::APPROPRIATION_ORDINANCE,
            'appropriation_ordinance_id' => $staleAppropriation->id,
            'published_at' => now(),
        ]);

        $csv = $this->writeCsv([
            [
                ' ',
                'Request PDF',
                'Date Received',
                'Sender',
                'Title',
                'Status',
                'Prescribed Dates',
                'Date passed',
                'Reso./Ord./AO No.',
                'Resolution Title',
            ],
            [
                '301',
                '',
                '2026-03-01',
                'Balanga',
                'Request title',
                'Done',
                '30',
                '2026-03-20',
                '301',
                'Matching resolution',
            ],
        ]);

        app(AgendaCsvImporter::class)->sync($csv, linksPath: $this->missingLinksPath(), dryRun: false);

        $agenda = AgendaItem::query()->where('tracking_no', '301')->first();

        $this->assertNotNull($agenda);
        $this->assertSame($resolution->id, $agenda->resolution_id);
        $this->assertNull($agenda->ordinance_id);
        $this->assertSame(AgendaMeasureType::RESOLUTION, $agenda->reso_ord_ao_type);
        $this->assertSame(AgendaItem::OUTPUT_CONNECTION_LINKED, $agenda->output_connection_type);
        $this->assertSame('Matching resolution', $resolution->fresh()->resolution_title);
    }

    public function test_import_does_not_create_output_when_no_match_exists(): void
    {
        $csv = $this->writeCsv([
            [
                ' ',
                'Request PDF',
                'Date Received',
                'Sender',
                'Title',
                'Status',
                'Prescribed Dates',
                'Date passed',
                'Reso./Ord./AO No.',
                'Resolution Title',
            ],
            [
                '777',
                '',
                '2026-03-01',
                'Balanga',
                'Unmatched output',
                'Done',
                '30',
                '2026-03-20',
                '777',
                'Resolution with no existing SPLIS output',
            ],
        ]);

        app(AgendaCsvImporter::class)->sync($csv, linksPath: $this->missingLinksPath(), dryRun: false);

        $agenda = AgendaItem::query()->where('tracking_no', '777')->first();

        $this->assertNotNull($agenda);
        $this->assertSame('777', $agenda->reso_ord_ao_no);
        $this->assertNull($agenda->resolution_id);
        $this->assertNull($agenda->ordinance_id);
        $this->assertNull($agenda->appropriation_ordinance_id);
        $this->assertNull($agenda->published_at);
        $this->assertNull($agenda->output_connection_type);
        $this->assertSame(0, Resolution::query()->count());
        $this->assertSame(0, Ordinance::query()->count());
        $this->assertSame(0, AppropriationOrdinance::query()->count());
    }

    public function test_pending_import_does_not_keep_stale_output_link(): void
    {
        $resolution = Resolution::query()->create([
            'resolution_no' => '88',
            'resolution_title' => 'Stale link',
            'series' => 2026,
            'status' => 'approved',
        ]);

        AgendaItem::query()->create([
            'tracking_no' => '088',
            'sender' => 'Orion',
            'title' => 'Still pending',
            'date_received' => '2026-04-01',
            'status' => AgendaItem::STATUS_DONE,
            'prescribed_days' => 30,
            'reso_ord_ao_no' => '88',
            'reso_ord_ao_series' => 2026,
            'resolution_id' => $resolution->id,
            'published_at' => now(),
        ]);

        $csv = $this->writeCsv([
            [' ', 'Request PDF', 'Date Received', 'Sender', 'Title', 'Status', 'Prescribed Dates'],
            ['088', '', '2026-04-01', 'Orion', 'Still pending', 'Pending', '30'],
        ]);

        app(AgendaCsvImporter::class)->sync($csv, linksPath: $this->missingLinksPath(), dryRun: false);

        $agenda = AgendaItem::query()->where('tracking_no', '088')->first();

        $this->assertNotNull($agenda);
        $this->assertSame(AgendaItem::STATUS_PENDING, $agenda->status);
        $this->assertNull($agenda->resolution_id);
        $this->assertNull($agenda->ordinance_id);
        $this->assertNull($agenda->published_at);
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
