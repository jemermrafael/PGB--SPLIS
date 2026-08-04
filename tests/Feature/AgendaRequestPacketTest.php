<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\AgendaItemRequestFile;
use App\Models\User;
use App\Services\AgendaItemRequestFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgendaRequestPacketTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_local_folders_preserves_folder_names(): void
    {
        Storage::fake('local');

        $encoder = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $agenda = AgendaItem::query()->create([
            'tracking_no' => '318',
            'title' => 'Multi-file request packet',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $encoder->id,
        ]);

        Storage::disk('local')->put(
            'agenda/'.$agenda->id.'/FOR RECOGNITION/1. BATAAN PENINSULA TOUR GUIDES.pdf',
            '%PDF-1.4 fake recognition',
        );
        Storage::disk('local')->put(
            'agenda/'.$agenda->id.'/FOR ACCREDITATION/1. BOSS LEAGUE OF BATAAN.pdf',
            '%PDF-1.4 fake accreditation',
        );
        Storage::disk('local')->put(
            'agenda/'.$agenda->id.'/Letter from OPPDO.pdf',
            '%PDF-1.4 fake letter',
        );
        Storage::disk('local')->put(
            'agenda/'.$agenda->id.'/request.pdf',
            '%PDF-1.4 single request slot',
        );

        $this->actingAs($encoder)
            ->post(route('agenda.request-files.import-disk', $agenda))
            ->assertRedirect(route('agenda.show', $agenda));

        $files = $agenda->fresh()->requestFiles;
        $this->assertCount(3, $files);
        $this->assertTrue($files->contains(fn (AgendaItemRequestFile $file) => $file->relative_folder === 'FOR RECOGNITION'
            && $file->original_filename === '1. BATAAN PENINSULA TOUR GUIDES.pdf'));
        $this->assertTrue($files->contains(fn (AgendaItemRequestFile $file) => $file->relative_folder === 'FOR ACCREDITATION'
            && $file->original_filename === '1. BOSS LEAGUE OF BATAAN.pdf'));
        $this->assertTrue($files->contains(fn (AgendaItemRequestFile $file) => $file->relative_folder === null
            && $file->original_filename === 'Letter from OPPDO.pdf'));

        $this->assertSame(
            $files->firstWhere('original_filename', 'Letter from OPPDO.pdf')?->stored_path,
            $agenda->fresh()->request_pdf_path,
        );
    }

    public function test_root_packet_pdf_becomes_primary_request_pdf(): void
    {
        Storage::fake('local');

        $encoder = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $agenda = AgendaItem::query()->create([
            'tracking_no' => '107',
            'title' => 'Root is request pdf',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $encoder->id,
        ]);

        $this->actingAs($encoder)
            ->post(route('agenda.request-files.store', $agenda), [
                'relative_folder' => '',
                'request_packet_files' => [
                    UploadedFile::fake()->create('1. BATAAN PENINSULA TOUR GUIDES.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('agenda.show', $agenda));

        $agenda->refresh();
        $root = $agenda->requestFiles()->first();

        $this->assertNotNull($root);
        $this->assertNull($root->relative_folder);
        $this->assertSame($root->stored_path, $agenda->request_pdf_path);

        $this->actingAs($encoder)
            ->get(route('agenda.show', $agenda))
            ->assertOk()
            ->assertSee('Request PDF')
            ->assertSee('Request packet');
    }

    public function test_upload_to_named_folder_does_not_create_agenda_version(): void
    {
        Storage::fake('local');

        $encoder = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $agenda = AgendaItem::query()->create([
            'tracking_no' => '319',
            'title' => 'Upload packet',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'current_version_no' => 1,
            'created_by' => $encoder->id,
        ]);

        $beforeVersions = $agenda->versions()->count();

        $this->actingAs($encoder)
            ->post(route('agenda.request-files.store', $agenda), [
                'relative_folder' => 'FOR RECOGNITION',
                'request_packet_files' => [
                    UploadedFile::fake()->create('guides.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('agenda.show', $agenda));

        $this->assertSame(1, $agenda->fresh()->requestFiles()->count());
        $this->assertSame('FOR RECOGNITION', $agenda->requestFiles()->first()->relative_folder);
        $this->assertSame($beforeVersions, $agenda->fresh()->versions()->count());
    }

    public function test_show_page_lists_request_packet_grouped_by_folder(): void
    {
        Storage::fake('local');

        $encoder = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $agenda = AgendaItem::query()->create([
            'tracking_no' => '320',
            'title' => 'Show packet',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $encoder->id,
        ]);

        app(AgendaItemRequestFileService::class)->storeBytes(
            '%PDF-1.4 content',
            $agenda,
            '1. BOSS LEAGUE.pdf',
            'FOR ACCREDITATION',
            'pdf',
            'application/pdf',
            $encoder->id,
        );

        $this->actingAs($encoder)
            ->get(route('agenda.show', $agenda))
            ->assertOk()
            ->assertSee('Request packet')
            ->assertSee('FOR ACCREDITATION')
            ->assertSee('1. BOSS LEAGUE.pdf');
    }
}
