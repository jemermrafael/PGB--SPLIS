<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\Municipality;
use App\Models\Resolution;
use App\Models\User;
use App\Services\ResolutionCsvImporter;
use App\Services\ResolutionVersionService;
use App\Support\DocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ResolutionFiltersAndSyncDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_filter_resolutions_published_from_agenda(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $fromAgenda = Resolution::query()->create([
            'resolution_no' => '10',
            'resolution_title' => 'From agenda',
            'series' => 2026,
            'status' => 'approved',
            'document_type' => DocumentType::RESOLUTION,
            'created_by' => $superadmin->id,
        ]);

        Resolution::query()->create([
            'resolution_no' => '11',
            'resolution_title' => 'Manual',
            'series' => 2026,
            'status' => 'approved',
            'document_type' => DocumentType::RESOLUTION,
            'created_by' => $superadmin->id,
        ]);

        AgendaItem::query()->create([
            'tracking_no' => '7001',
            'sender' => 'Balanga',
            'title' => 'Linked agenda',
            'status' => AgendaItem::STATUS_DONE,
            'resolution_id' => $fromAgenda->id,
            'reso_ord_ao_type' => 'resolution',
            'created_by' => $superadmin->id,
        ]);

        $this->actingAs($superadmin)
            ->getJson(route('resolutions.search', ['from_agenda' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.number', '10');
    }

    public function test_non_superadmin_cannot_use_from_agenda_filter(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        $fromAgenda = Resolution::query()->create([
            'resolution_no' => '20',
            'resolution_title' => 'From agenda',
            'series' => 2026,
            'status' => 'approved',
            'document_type' => DocumentType::RESOLUTION,
            'created_by' => $encoder->id,
        ]);

        Resolution::query()->create([
            'resolution_no' => '21',
            'resolution_title' => 'Manual',
            'series' => 2026,
            'status' => 'approved',
            'document_type' => DocumentType::RESOLUTION,
            'created_by' => $encoder->id,
        ]);

        AgendaItem::query()->create([
            'tracking_no' => '7002',
            'sender' => 'Orion',
            'title' => 'Linked agenda',
            'status' => AgendaItem::STATUS_DONE,
            'resolution_id' => $fromAgenda->id,
            'reso_ord_ao_type' => 'resolution',
            'created_by' => $encoder->id,
        ]);

        $this->actingAs($encoder)
            ->getJson(route('resolutions.search', ['from_agenda' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_resolution_csv_sync_lists_duplicate_and_conflict_details(): void
    {
        Resolution::query()->create([
            'resolution_no' => '55',
            'resolution_title' => 'Existing active',
            'series' => 2026,
            'status' => 'approved',
            'document_type' => DocumentType::RESOLUTION,
            'legacy_sp_id' => 999,
        ]);

        $csv = <<<'CSV'
ID,Resolution_No,Series,Resolution_Title,Office,Date_App_En,Sponsored_By,Category,Sub_Cat1,Sub_Cat2,Sub_Cat3,Keyword,Comittee,App_Ord_No,Amount,Municipality,Province
1,100,2026,First,,,,,,,,
2,100,2026,Duplicate pair,,,,,,,,
3,55,2026,Conflicts with existing,,,,,,,,
CSV;

        $path = tempnam(sys_get_temp_dir(), 'reso-sync-');
        file_put_contents($path, $csv);

        $stats = app(ResolutionCsvImporter::class)->sync(
            dryRun: true,
            spFilePath: $path,
        );

        @unlink($path);

        $this->assertSame(1, $stats['csv_duplicate_number_series']);
        $this->assertContains('2026/100', $stats['duplicate_number_series_pairs']);
        $this->assertSame(1, $stats['conflicting_active_number']);
        $this->assertNotEmpty($stats['conflicting_active_number_details']);
        $this->assertStringContainsString('2026/55', $stats['conflicting_active_number_details'][0]);
        $this->assertStringContainsString('CSV ID 3', $stats['conflicting_active_number_details'][0]);
    }

    public function test_data_sync_flash_includes_duplicate_pair_details(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        Resolution::query()->create([
            'resolution_no' => '55',
            'resolution_title' => 'Existing active',
            'series' => 2026,
            'status' => 'approved',
            'document_type' => DocumentType::RESOLUTION,
            'legacy_sp_id' => 999,
        ]);

        $csv = <<<'CSV'
ID,Resolution_No,Series,Resolution_Title,Office,Date_App_En,Sponsored_By,Category,Sub_Cat1,Sub_Cat2,Sub_Cat3,Keyword,Comittee,App_Ord_No,Amount,Municipality,Province
1,100,2026,First,,,,,,,,
2,100,2026,Duplicate pair,,,,,,,,
3,55,2026,Conflicts with existing,,,,,,,,
CSV;

        $upload = UploadedFile::fake()->createWithContent('reso6.csv', $csv);

        $response = $this->actingAs($superadmin)
            ->post(route('admin.data-sync.resolutions'), [
                'sp_csv' => $upload,
                'dry_run' => '1',
            ])
            ->assertRedirect();

        $status = (string) session('status');
        $this->assertStringContainsString('2026/100', $status);
        $this->assertStringContainsString('2026/55', $status);
        $this->assertStringContainsString('CSV ID 3', $status);
    }

    public function test_updated_csv_uses_municipality_code_and_resets_history_to_imported_v1(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
        $samal = Municipality::query()->create([
            'code' => 12,
            'description' => 'SAMAL',
        ]);

        $resolution = Resolution::query()->create([
            'legacy_sp_id' => 11607,
            'resolution_no' => '2026-303',
            'resolution_title' => 'Old title',
            'series' => 2026,
            'status' => 'approved',
            'document_type' => DocumentType::RESOLUTION,
            'created_by' => $superadmin->id,
        ]);

        $versions = app(ResolutionVersionService::class);
        $versions->recordInitialVersion($resolution, $superadmin->id);
        $before = $versions->snapshotFrom($resolution);
        $resolution->update(['resolution_title' => 'Manually edited title']);
        $versions->recordVersionIfChanged($resolution->fresh(), $before, $superadmin->id);
        $this->assertSame(2, $resolution->versions()->count());

        $path = tempnam(sys_get_temp_dir(), 'reso-updated-columns-');
        $handle = fopen($path, 'w');
        fputcsv($handle, [
            'ID', 'Resolution_No', 'Resolution_Title', 'Series', 'Office', 'Date_App_En',
            'Sponsored_By', 'zCategory1', 'zCategory2', 'zcategory3', 'zcategory4',
            'Keyword', 'Comittee', 'App_Ord_No', 'Amount', 'Municipality', 'Province',
            'Category', 'Sub_Cat1', 'Sub_Cat2', 'Sub_Cat3', 'LinkFile', 'MunCode',
        ]);
        fputcsv($handle, [
            11607, '2026-303', 'Imported updated title', 2026, '', '07-13',
            'BM RAMON HAROLD ESPELETA', 'Concurrence Municipal Ordinance',
            'RECLASSIFICATION', 'AGRICULTURAL TO COMMERCIAL USE', '',
            'RECLASSIFICATION, SAMAL', 'HOUSING', '', '0.0000', 'SAMAL', 'false',
            29, 3056, 3604, 978, '', 12,
        ]);
        fclose($handle);

        app(ResolutionCsvImporter::class)->sync(
            dryRun: false,
            spFilePath: $path,
            userId: $superadmin->id,
        );
        @unlink($path);

        $resolution->refresh();
        $this->assertSame($samal->id, $resolution->municipality_id);
        $this->assertSame('Imported updated title', $resolution->resolution_title);
        $this->assertSame(1, $resolution->current_version_no);
        $this->assertSame(1, $resolution->versions()->count());

        $version = $resolution->versions()->first();
        $this->assertSame(1, $version->version_no);
        $this->assertSame('imported', $version->change_reason);
        $this->assertSame($superadmin->id, $version->created_by);
        $this->assertSame($samal->id, $version->snapshotValue('municipality_id'));
    }

    public function test_updated_csv_can_fall_back_to_municipality_name(): void
    {
        $samal = Municipality::query()->create([
            'code' => 12,
            'description' => 'Samal',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'reso-municipality-name-');
        $handle = fopen($path, 'w');
        fputcsv($handle, [
            'ID', 'Resolution_No', 'Resolution_Title', 'Series', 'Municipality', 'MunCode',
        ]);
        fputcsv($handle, [11608, '2026-304', 'Name fallback', 2026, '  samal  ', '']);
        fclose($handle);

        app(ResolutionCsvImporter::class)->sync(
            dryRun: false,
            spFilePath: $path,
        );
        @unlink($path);

        $this->assertDatabaseHas('resolutions', [
            'legacy_sp_id' => 11608,
            'municipality_id' => $samal->id,
        ]);
    }
}
