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
            ->assertSee('Google Drive link (.docx)', false)
            ->assertDontSee('Draft Journal (upload)', false)
            ->assertDontSee('Summary of Comm. Reports (upload)', false)
            ->assertDontSee('Summary of Comm. Reports URL (fallback)', false)
            ->assertSee('Open Maker', false)
            ->assertSee('Preview', false)
            ->assertSee('Download linked files');
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

    public function test_removing_committee_report_file_keeps_session(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $session = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $storedPath = 'order-of-business/'.$session->id.'/committee-reports/report-a.pdf';
        Storage::disk('local')->put($storedPath, '%PDF-1.4 test');

        $file = LegislativeSessionCommitteeReportFile::create([
            'legislative_session_id' => $session->id,
            'original_filename' => 'report-a.pdf',
            'stored_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'sort_order' => 1,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('ob.sessions.committee-report-file.destroy', [$session, $file]))
            ->assertRedirect(route('ob.sessions.edit', $session));

        $this->assertDatabaseMissing('legislative_session_committee_report_files', ['id' => $file->id]);
        $this->assertNotNull($session->fresh(), 'Session must not be deleted when removing a committee report file.');
        $this->assertNull($session->fresh()->deleted_at);
        Storage::disk('local')->assertMissing($storedPath);
    }

    public function test_deleting_local_session_pdf_clears_path_and_keeps_session(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $session = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'pdf_final_journal' => 'https://drive.google.com/file/d/example/view',
            'created_by' => $admin->id,
        ]);

        $storedPath = 'order-of-business/'.$session->id.'/final-journal.pdf';
        Storage::disk('local')->put($storedPath, '%PDF-1.4 test');
        $session->update(['pdf_final_journal_path' => $storedPath]);

        $this->actingAs($admin)
            ->delete(route('ob.sessions.pdf.destroy', [$session, 'pdf_final_journal']))
            ->assertRedirect(route('ob.sessions.edit', $session));

        $session->refresh();

        $this->assertNull($session->pdf_final_journal_path);
        $this->assertSame('https://drive.google.com/file/d/example/view', $session->pdf_final_journal);
        $this->assertNull($session->deleted_at);
        Storage::disk('local')->assertMissing($storedPath);
    }

    public function test_committee_reports_folder_mirror_downloads_files(): void
    {
        Storage::fake('local');
        \Illuminate\Support\Facades\Http::fake([
            'drive.google.com/embeddedfolderview*' => \Illuminate\Support\Facades\Http::response(
                '<a href="https://drive.google.com/file/d/fileOne/view">Alpha.pdf</a>'
                .'<a href="https://drive.google.com/file/d/fileTwo/view">Beta.pdf</a>',
                200,
            ),
            'drive.google.com/uc?*id=fileOne*' => \Illuminate\Support\Facades\Http::response('%PDF-1.4 one', 200, [
                'Content-Type' => 'application/pdf',
            ]),
            'drive.google.com/uc?*id=fileTwo*' => \Illuminate\Support\Facades\Http::response('%PDF-1.4 two', 200, [
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $session = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'pdf_committee_reports' => 'https://drive.google.com/drive/folders/folderAbc',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('ob.sessions.committee-reports.mirror', $session))
            ->assertRedirect(route('ob.sessions.edit', $session));

        $session->refresh();
        $this->assertSame(2, $session->committeeReportFiles()->count());
        $this->assertTrue(
            $session->committeeReportFiles->contains(fn ($file) => $file->original_filename === 'Alpha.pdf')
        );
        $this->assertTrue(
            $session->committeeReportFiles->contains(fn ($file) => $file->original_filename === 'Beta.pdf')
        );

        // Second run should skip existing filenames.
        $this->actingAs($admin)
            ->post(route('ob.sessions.committee-reports.mirror', $session))
            ->assertRedirect(route('ob.sessions.edit', $session));

        $this->assertSame(2, $session->committeeReportFiles()->count());
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
                'pdf_final_journal_file' => UploadedFile::fake()->create('final-journal.pdf', 100, 'application/pdf'),
                'committee_report_files' => [
                    UploadedFile::fake()->create('committee-a.pdf', 100, 'application/pdf'),
                    UploadedFile::fake()->create('committee-b.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('ob.sessions.show', $session));

        $session->refresh();

        $this->assertNotNull($session->pdf_final_journal_path);
        $this->assertSame(2, $session->committeeReportFiles()->count());
        Storage::disk('local')->assertExists($session->pdf_final_journal_path);
    }

    public function test_update_saves_draft_journal_and_minutes_as_external_links_only(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $session = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $journalUrl = 'https://drive.google.com/file/d/journalDoc/view';
        $minutesUrl = 'https://drive.google.com/file/d/minutesDoc/view';

        $this->actingAs($admin)
            ->put(route('ob.sessions.update', $session), [
                'session_date' => $session->session_date->format('Y-m-d'),
                'session_kind' => 'regular',
                'status' => 'draft',
                'pdf_draft_journal' => $journalUrl,
                'pdf_draft_minutes' => $minutesUrl,
            ])
            ->assertRedirect(route('ob.sessions.show', $session));

        $session->refresh();

        $this->assertSame($journalUrl, $session->pdf_draft_journal);
        $this->assertSame($minutesUrl, $session->pdf_draft_minutes);
        $this->assertNull($session->pdf_draft_journal_path);
        $this->assertNull($session->pdf_draft_minutes_path);

        $this->actingAs($admin)
            ->get(route('ob.sessions.show', $session))
            ->assertOk()
            ->assertSee($journalUrl, false)
            ->assertSee($minutesUrl, false)
            ->assertSee('Open link', false)
            ->assertDontSee('Draft Journal (upload)', false);
    }
}
