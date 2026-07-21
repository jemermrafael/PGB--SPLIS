<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\User;
use App\Services\AgendaCsvImporter;
use App\Services\AgendaVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgendaVersionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_import_records_initial_version(): void
    {
        $csv = $this->writeCsv([
            [' ', 'Request PDF', 'Date Received', 'Sender', 'Title', 'Status', 'Prescribed Dates'],
            ['330', '', '2026-06-01', 'Balanga', 'Imported agenda sample', 'Pending', '30'],
        ]);

        app(AgendaCsvImporter::class)->sync($csv, linksPath: $this->missingLinksPath(), dryRun: false);

        $agenda = AgendaItem::query()->where('tracking_no', '330')->first();
        $this->assertNotNull($agenda);
        $this->assertSame(1, $agenda->versions()->count());
        $this->assertSame(1, $agenda->current_version_no);
        $this->assertSame('encoded', $agenda->versions()->first()->change_reason);
    }

    public function test_backfill_creates_versions_for_items_without_history(): void
    {
        $agenda = AgendaItem::query()->create([
            'tracking_no' => '331',
            'sender' => 'Orion',
            'title' => 'No version yet',
            'status' => AgendaItem::STATUS_PENDING,
        ]);

        $this->assertSame(0, $agenda->versions()->count());

        $created = app(AgendaVersionService::class)->backfillMissingInitialVersions();

        $this->assertSame(1, $created);
        $agenda->refresh();
        $this->assertSame(1, $agenda->versions()->count());
        $this->assertSame(1, $agenda->current_version_no);
    }

    public function test_request_pdf_upload_creates_version_and_keeps_previous_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => UserRole::Encoder]);
        $agenda = AgendaItem::query()->create([
            'tracking_no' => '332',
            'sender' => 'Mariveles',
            'title' => 'PDF versioning',
            'status' => AgendaItem::STATUS_PENDING,
            'created_by' => $user->id,
        ]);

        $oldRelative = 'agenda/'.$agenda->id.'/request/old-file.pdf';
        Storage::disk('local')->put($oldRelative, '%PDF-1.4 old');
        $agenda->update(['request_pdf_path' => $oldRelative]);

        app(AgendaVersionService::class)->recordInitialVersion($agenda, $user->id);

        $newUpload = UploadedFile::fake()->create('replacement.pdf', 120, 'application/pdf');

        $this->actingAs($user)
            ->put(route('agenda.update', $agenda), [
                'tracking_no' => '332',
                'sender' => 'Mariveles',
                'title' => 'PDF versioning',
                'status' => AgendaItem::STATUS_PENDING,
                'request_pdf' => $newUpload,
            ])
            ->assertRedirect(route('agenda.show', $agenda));

        $agenda->refresh();
        $versions = $agenda->versions()->reorder()->orderBy('version_no')->get();

        $this->assertCount(2, $versions);
        $this->assertSame(1, $versions[0]->version_no);
        $this->assertSame(2, $versions[1]->version_no);
        $this->assertSame($oldRelative, $versions[0]->snapshotValue('request_pdf_path'));
        $this->assertNotSame($oldRelative, $agenda->request_pdf_path);
        $this->assertSame($agenda->request_pdf_path, $versions[1]->snapshotValue('request_pdf_path'));
        $this->assertTrue(Storage::disk('local')->exists($oldRelative));
        $this->assertTrue(Storage::disk('local')->exists($agenda->request_pdf_path));

        $this->actingAs($user)
            ->get(route('agenda.versions.file', [
                'agenda' => $agenda,
                'version' => $versions[0],
                'slot' => 'request',
            ]))
            ->assertOk();
    }

    /**
     * @param  list<list<string>>  $rows
     */
    protected function writeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'agenda-version-csv-');
        $handle = fopen($path, 'w');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }

    protected function missingLinksPath(): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'missing-agenda-links-'.uniqid('', true).'.csv';
    }
}
