<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\LegislativeSessionCommitteeReportFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SessionPdfMirrorUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_shows_upload_fields_and_mirror_button(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $session = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'pdf_committee_reports' => 'https://drive.google.com/drive/folders/example',
            'pdf_draft_journal' => 'https://drive.google.com/file/d/example/view',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('ob.sessions.edit', $session))
            ->assertOk()
            ->assertSee('Upload PDF files', false)
            ->assertSee('Google Drive folder link (fallback)', false)
            ->assertSee('Draft Journal (upload)', false)
            ->assertSee('Download linked PDFs');
    }

    public function test_show_page_uses_folder_modal_for_committee_reports(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $session = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'pdf_committee_reports' => 'https://drive.google.com/drive/folders/example',
            'created_by' => $admin->id,
        ]);

        $storedPath = 'order-of-business/'.$session->id.'/committee-reports/report-a.pdf';
        Storage::disk('local')->put($storedPath, '%PDF-1.4 test');

        LegislativeSessionCommitteeReportFile::create([
            'legislative_session_id' => $session->id,
            'original_filename' => 'report-a.pdf',
            'stored_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'sort_order' => 1,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('ob.sessions.show', $session))
            ->assertOk()
            ->assertSee('View folder (1)')
            ->assertSee('committee-reports-folder-modal')
            ->assertSee('report-a.pdf')
            ->assertSee('Open Drive folder');
    }

    public function test_update_stores_uploaded_session_pdf_and_committee_report_files(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $session = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('ob.sessions.update', $session), [
                'session_date' => $session->session_date->format('Y-m-d'),
                'session_kind' => 'regular',
                'status' => 'draft',
                'pdf_draft_journal_file' => UploadedFile::fake()->create('draft-journal.pdf', 100, 'application/pdf'),
                'committee_report_files' => [
                    UploadedFile::fake()->create('committee-a.pdf', 100, 'application/pdf'),
                    UploadedFile::fake()->create('committee-b.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('ob.sessions.show', $session));

        $session->refresh();

        $this->assertNotNull($session->pdf_draft_journal_path);
        $this->assertSame(2, $session->committeeReportFiles()->count());
        Storage::disk('local')->assertExists($session->pdf_draft_journal_path);
    }
}
