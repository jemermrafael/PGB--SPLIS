<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Ordinance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OrdinanceCsvImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_ordinances_from_csv_format(): void
    {
        $csv = <<<'CSV'
ORD NO.,GDrive,Publish Status,SUBJECT,DATE ENACTED,DATE APPROVED,POSTED IN CONSPICUOUS PLACES,PUBLISHED IN NEWSPAPER,EFFECTIVITY DATE,BULLETIN,BULLETIN,CERTIFICATION,CERTIFICATION GDrive,NEWSPAPER,NEWSPAPER  Gdrive,IMPLEMENTING BODIES/DEPT./AGENCIES/OFFICES,PERSPECTIVE CLASSIFICATION,MANDATE/PPA,REMARKS
1,https://drive.google.com/file/d/example/view,PUBLISHED,"An ordinance sample.",2/2/2026,2/10/2026,2/10/2026,4/1/2026-4/7/2026,2/20/2026,"2/10/2026 Ord No.1",https://drive.google.com/file/d/bulletin/view,ORD. NO 01,https://drive.google.com/file/d/cert/view,Bataan Tower News,https://drive.google.com/file/d/paper/view,OPCEDO,Citizen,PPA,Note
CSV;

        $path = storage_path('framework/testing/ordinances-sample.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $stats = app(\App\Services\OrdinanceCsvImporter::class)->sync(
            dryRun: false,
            csvFilePath: $path,
            seriesYear: 2026,
        );

        $this->assertSame(1, $stats['created']);

        $ordinance = Ordinance::query()->where('ordinance_no', 1)->where('series_year', 2026)->first();

        $this->assertNotNull($ordinance);
        $this->assertSame('An ordinance sample.', $ordinance->subject);
        $this->assertSame('published', $ordinance->publication_status?->value);
        $this->assertSame('https://drive.google.com/file/d/example/view', $ordinance->pdf_url);
        $this->assertSame('2026-02-02', $ordinance->date_enacted?->toDateString());
        $this->assertSame('2026-04-07', $ordinance->date_published_newspaper?->toDateString());
        $this->assertSame('https://drive.google.com/file/d/bulletin/view', $ordinance->mov_bulletin_url);
        $this->assertSame('Note', $ordinance->remarks);
    }

    public function test_maps_ord_2_from_current_csv_format(): void
    {
        $csv = <<<'CSV'
ORD NO.,GDrive,Publish Status,SUBJECT,DATE ENACTED,DATE APPROVED,POSTED IN CONSPICUOUS PLACES,PUBLISHED IN NEWSPAPER,EFFECTIVITY DATE,BULLETIN,BULLETIN,CERTIFICATION,CERTIFICATION GDrive,NEWSPAPER,NEWSPAPER  Gdrive,IMPLEMENTING BODIES/DEPT./AGENCIES/OFFICES,PERSPECTIVE CLASSIFICATION,MANDATE/PPA,REMARKS
2,https://drive.google.com/file/d/main/view,PUBLISHED,"An ordinance repealing Section 120.",2/16/2026,3/3/2026,3/3/2026,"4/1/2026-
4/7/2026",4/8/2026,"3/3/2026
Ord Nos. 2 & 3",https://drive.google.com/file/d/bulletin/view,ORD. NO. 02 & 03,https://drive.google.com/file/d/cert/view,Bataan Tower News,https://drive.google.com/file/d/paper/view,FINANCE TEAM,Citizen,PPA,
CSV;

        $path = storage_path('framework/testing/ordinances-002.csv');
        file_put_contents($path, $csv);

        app(\App\Services\OrdinanceCsvImporter::class)->sync(
            dryRun: false,
            csvFilePath: $path,
            seriesYear: 2026,
        );

        $ordinance = Ordinance::query()->where('ordinance_no', 2)->where('series_year', 2026)->first();

        $this->assertNotNull($ordinance);
        $this->assertSame('published', $ordinance->publication_status?->value);
        $this->assertSame('https://drive.google.com/file/d/main/view', $ordinance->pdf_url);
        $this->assertSame('2026-04-07', $ordinance->date_published_newspaper?->toDateString());
        $this->assertSame('Bataan Tower News', $ordinance->mov_newspaper);
        $this->assertSame('https://drive.google.com/file/d/paper/view', $ordinance->mov_newspaper_url);
        $this->assertSame('FINANCE TEAM', $ordinance->implementing_bodies);
    }

    public function test_superadmin_can_sync_ordinances_from_data_sync_upload(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $csv = <<<'CSV'
ORD NO.,GDrive,Publish Status,SUBJECT,DATE ENACTED,DATE APPROVED,POSTED IN CONSPICUOUS PLACES,PUBLISHED IN NEWSPAPER,EFFECTIVITY DATE,BULLETIN,BULLETIN,CERTIFICATION,CERTIFICATION GDrive,NEWSPAPER,NEWSPAPER  Gdrive,IMPLEMENTING BODIES/DEPT./AGENCIES/OFFICES,PERSPECTIVE CLASSIFICATION,MANDATE/PPA,REMARKS
2,,FOR PUBLICATION,"Second ordinance.",3/1/2026,3/5/2026,,,,,,,,,,,Citizen,PPA,
CSV;

        $upload = UploadedFile::fake()->createWithContent('Ordinances-001.csv', $csv);

        $this->actingAs($superadmin)
            ->post(route('admin.data-sync.ordinances'), [
                'ordinances_csv' => $upload,
                'series_year' => 2026,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('ordinances', [
            'ordinance_no' => 2,
            'series_year' => 2026,
            'subject' => 'Second ordinance.',
            'publication_status' => 'for_publication',
        ]);
    }
}
