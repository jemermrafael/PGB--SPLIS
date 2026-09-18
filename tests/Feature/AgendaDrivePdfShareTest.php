<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\User;
use App\Services\AgendaDrivePdfShareService;
use App\Services\AgendaPdfMirrorService;
use App\Services\AgendaPdfService;
use App\Support\AgendaPdfSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgendaDrivePdfShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_mirror_reuses_existing_local_file_for_same_drive_url(): void
    {
        Storage::fake('local');

        $encoder = User::factory()->create(['role' => UserRole::Encoder]);

        $first = AgendaItem::create([
            'tracking_no' => '100',
            'title' => 'First',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'committee_report_url' => 'https://drive.google.com/file/d/ABC123/view',
            'created_by' => $encoder->id,
        ]);

        $sharedPath = 'agenda/'.$first->id.'/committee-report.pdf';
        Storage::disk('local')->put($sharedPath, '%PDF-1.4 shared');
        $first->forceFill(['committee_report_pdf_path' => $sharedPath])->save();

        $second = AgendaItem::create([
            'tracking_no' => '101',
            'title' => 'Second',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'committee_report_url' => 'https://drive.google.com/open?id=ABC123',
            'created_by' => $encoder->id,
        ]);

        Http::fake([
            '*' => Http::response('should-not-download', 500),
        ]);

        $result = app(AgendaPdfMirrorService::class)->mirror($second, AgendaPdfSlot::COMMITTEE_REPORT);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('reused existing local file', $result['message']);
        $this->assertSame($sharedPath, $second->fresh()->committee_report_pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($sharedPath));
        $this->assertFalse(Storage::disk('local')->exists('agenda/'.$second->id.'/committee-report.pdf'));
        $this->assertSame($first->id, AgendaItem::query()->where('committee_report_pdf_path', $sharedPath)->orderBy('id')->value('id'));
    }

    public function test_dedupe_relinks_agendas_and_purges_duplicate_files(): void
    {
        Storage::fake('local');

        $encoder = User::factory()->create(['role' => UserRole::Encoder]);
        $keeper = 'agenda/10/committee-report.pdf';
        $duplicate = 'agenda/11/committee-report.pdf';

        Storage::disk('local')->put($keeper, '%PDF-1.4 keeper');
        Storage::disk('local')->put($duplicate, '%PDF-1.4 duplicate');

        $a = AgendaItem::create([
            'tracking_no' => '200',
            'title' => 'A',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'committee_report_url' => 'https://drive.google.com/file/d/XYZ999/view?usp=sharing',
            'committee_report_pdf_path' => $keeper,
            'created_by' => $encoder->id,
        ]);

        $b = AgendaItem::create([
            'tracking_no' => '201',
            'title' => 'B',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'committee_report_url' => 'https://drive.google.com/uc?id=XYZ999&export=download',
            'committee_report_pdf_path' => $duplicate,
            'created_by' => $encoder->id,
        ]);

        $result = app(AgendaDrivePdfShareService::class)->dedupe();

        $this->assertSame(1, $result['groups']);
        $this->assertSame(1, $result['linked']);
        $this->assertSame(1, $result['purged']);
        $this->assertSame($keeper, $a->fresh()->committee_report_pdf_path);
        $this->assertSame($keeper, $b->fresh()->committee_report_pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($keeper));
        $this->assertFalse(Storage::disk('local')->exists($duplicate));
    }

    public function test_superadmin_can_dedupe_from_data_sync(): void
    {
        Storage::fake('local');

        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
        $encoder = User::factory()->create(['role' => UserRole::Encoder]);
        $keeper = 'agenda/20/committee-report.pdf';
        $duplicate = 'agenda/21/committee-report.pdf';
        Storage::disk('local')->put($keeper, '%PDF-1.4');
        Storage::disk('local')->put($duplicate, '%PDF-1.4');

        AgendaItem::create([
            'tracking_no' => '300',
            'title' => 'A',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'committee_report_url' => 'https://drive.google.com/file/d/SHARE1/view',
            'committee_report_pdf_path' => $keeper,
            'created_by' => $encoder->id,
        ]);
        AgendaItem::create([
            'tracking_no' => '301',
            'title' => 'B',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'committee_report_url' => 'https://drive.google.com/file/d/SHARE1/view',
            'committee_report_pdf_path' => $duplicate,
            'created_by' => $encoder->id,
        ]);

        $this->actingAs($superadmin)
            ->post(route('admin.data-sync.agenda-drive-pdfs.dedupe'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertFalse(Storage::disk('local')->exists($duplicate));
    }

    public function test_store_bytes_on_keeper_rehomes_shared_path_for_other_agendas(): void
    {
        Storage::fake('local');

        $encoder = User::factory()->create(['role' => UserRole::Encoder]);

        $a = AgendaItem::create([
            'tracking_no' => '400',
            'title' => 'Keeper',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'committee_report_url' => 'https://drive.google.com/file/d/REHOME1/view',
            'created_by' => $encoder->id,
        ]);

        $keeper = 'agenda/'.$a->id.'/committee-report.pdf';
        Storage::disk('local')->put($keeper, '%PDF-1.4 shared-original');
        $a->forceFill(['committee_report_pdf_path' => $keeper])->save();

        $b = AgendaItem::create([
            'tracking_no' => '401',
            'title' => 'Sharer',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'committee_report_url' => 'https://drive.google.com/file/d/REHOME1/view',
            'committee_report_pdf_path' => $keeper,
            'created_by' => $encoder->id,
        ]);

        $path = app(AgendaPdfService::class)->storeBytes('%PDF-1.4 new-for-keeper', $a->fresh(), AgendaPdfSlot::COMMITTEE_REPORT, 'pdf');

        $this->assertSame($keeper, $path);
        $this->assertSame('%PDF-1.4 new-for-keeper', Storage::disk('local')->get($keeper));

        $bPath = $b->fresh()->committee_report_pdf_path;
        $this->assertSame('agenda/'.$b->id.'/committee-report.pdf', $bPath);
        $this->assertTrue(Storage::disk('local')->exists($bPath));
        $this->assertSame('%PDF-1.4 shared-original', Storage::disk('local')->get($bPath));
    }

    public function test_store_bytes_sibling_delete_keeps_shared_pdf_for_other_agenda(): void
    {
        Storage::fake('local');

        $encoder = User::factory()->create(['role' => UserRole::Encoder]);

        $a = AgendaItem::create([
            'tracking_no' => '410',
            'title' => 'Keeper',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $encoder->id,
        ]);

        $sharedPdf = 'agenda/'.$a->id.'/committee-report.pdf';
        Storage::disk('local')->put($sharedPdf, '%PDF-1.4 keep-me');
        $a->forceFill(['committee_report_pdf_path' => $sharedPdf])->save();

        $b = AgendaItem::create([
            'tracking_no' => '411',
            'title' => 'Sharer',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'committee_report_pdf_path' => $sharedPdf,
            'created_by' => $encoder->id,
        ]);

        app(AgendaPdfService::class)->storeBytes('fake-image-bytes', $a->fresh(), AgendaPdfSlot::COMMITTEE_REPORT, 'jpg');

        $bPath = $b->fresh()->committee_report_pdf_path;
        $this->assertSame('agenda/'.$b->id.'/committee-report.pdf', $bPath);
        $this->assertTrue(Storage::disk('local')->exists($bPath));
        $this->assertSame('%PDF-1.4 keep-me', Storage::disk('local')->get($bPath));
        $this->assertTrue(Storage::disk('local')->exists('agenda/'.$a->id.'/committee-report.jpg'));
        $this->assertNotSame($sharedPdf, $bPath);
    }
}
