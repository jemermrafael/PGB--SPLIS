<?php

namespace Tests\Feature;

use App\Enums\ObBlockType;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SessionFinalMinutesTaggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_minutes_upload_with_default_tags_applies_shared_minutes_path(): void
    {
        Storage::fake('local');

        [$admin, $session, $agendaA, $agendaB] = $this->seedSessionWithCommitteeReportAgendas();

        $this->actingAs($admin)
            ->put(route('ob.sessions.update', $session), $this->sessionPayload($session, [
                'pdf_final_minutes_file' => UploadedFile::fake()->create('final-minutes.pdf', 120, 'application/pdf'),
                'final_minutes_agenda_ids' => [$agendaA->id, $agendaB->id],
            ]))
            ->assertRedirect(route('ob.sessions.show', $session));

        $session->refresh();
        $this->assertNotNull($session->pdf_final_minutes_path);

        $agendaA->refresh();
        $agendaB->refresh();
        $this->assertSame($session->pdf_final_minutes_path, $agendaA->minutes_pdf_path);
        $this->assertSame($session->pdf_final_minutes_path, $agendaB->minutes_pdf_path);
        $this->assertDatabaseHas('legislative_session_final_minutes_agenda_item', [
            'legislative_session_id' => $session->id,
            'agenda_item_id' => $agendaA->id,
        ]);
        $this->assertDatabaseHas('legislative_session_final_minutes_agenda_item', [
            'legislative_session_id' => $session->id,
            'agenda_item_id' => $agendaB->id,
        ]);
    }

    public function test_untagging_clears_shared_minutes_path_but_keeps_agenda_owned_path(): void
    {
        Storage::fake('local');

        [$admin, $session, $agendaA, $agendaB] = $this->seedSessionWithCommitteeReportAgendas();

        $sessionPath = 'order-of-business/'.$session->id.'/final-minutes.pdf';
        Storage::disk('local')->put($sessionPath, '%PDF-1.4 session minutes');
        $session->update(['pdf_final_minutes_path' => $sessionPath]);

        $ownedPath = 'agenda/'.$agendaB->id.'/minutes.pdf';
        Storage::disk('local')->put($ownedPath, '%PDF-1.4 agenda minutes');

        $agendaA->update(['minutes_pdf_path' => $sessionPath]);
        $agendaB->update(['minutes_pdf_path' => $ownedPath]);

        $session->finalMinutesAgendaItems()->sync([$agendaA->id, $agendaB->id]);

        // Untag B (owned minutes) and A (shared minutes). Keep neither? Keep only neither for clear both cases:
        // Untag A so shared path clears; untag B so owned path is preserved.
        $this->actingAs($admin)
            ->put(route('ob.sessions.update', $session), $this->sessionPayload($session, [
                'final_minutes_agenda_ids' => [],
            ]))
            ->assertRedirect(route('ob.sessions.show', $session));

        $agendaA->refresh();
        $agendaB->refresh();
        $this->assertNull($agendaA->minutes_pdf_path);
        $this->assertSame($ownedPath, $agendaB->minutes_pdf_path);
        $this->assertDatabaseMissing('legislative_session_final_minutes_agenda_item', [
            'legislative_session_id' => $session->id,
            'agenda_item_id' => $agendaA->id,
        ]);
        $this->assertDatabaseMissing('legislative_session_final_minutes_agenda_item', [
            'legislative_session_id' => $session->id,
            'agenda_item_id' => $agendaB->id,
        ]);
    }

    public function test_edit_form_lists_committee_report_agendas_prechecked(): void
    {
        [$admin, $session, $agendaA, $agendaB] = $this->seedSessionWithCommitteeReportAgendas();

        $response = $this->actingAs($admin)
            ->get(route('ob.sessions.edit', $session))
            ->assertOk()
            ->assertSee('Apply Final Minutes to IV. Committee Report agendas', false)
            ->assertSee('Apply Final Journal to IV. Committee Report agendas', false)
            ->assertSee('#'.$agendaA->tracking_no, false)
            ->assertSee('#'.$agendaB->tracking_no, false);

        $html = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/name="final_minutes_agenda_ids\[\]"[^>]*value="'.$agendaA->id.'"[^>]*checked/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/name="final_minutes_agenda_ids\[\]"[^>]*value="'.$agendaB->id.'"[^>]*checked/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/name="final_journal_agenda_ids\[\]"[^>]*value="'.$agendaA->id.'"[^>]*checked/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/name="final_journal_agenda_ids\[\]"[^>]*value="'.$agendaB->id.'"[^>]*checked/',
            $html,
        );
    }

    public function test_final_journal_upload_with_default_tags_applies_shared_journal_path(): void
    {
        Storage::fake('local');

        [$admin, $session, $agendaA, $agendaB] = $this->seedSessionWithCommitteeReportAgendas();

        $this->actingAs($admin)
            ->put(route('ob.sessions.update', $session), $this->sessionPayload($session, [
                'pdf_final_journal_file' => UploadedFile::fake()->create('final-journal.pdf', 120, 'application/pdf'),
                'final_journal_agenda_ids' => [$agendaA->id, $agendaB->id],
            ]))
            ->assertRedirect(route('ob.sessions.show', $session));

        $session->refresh();
        $this->assertNotNull($session->pdf_final_journal_path);

        $agendaA->refresh();
        $agendaB->refresh();
        $this->assertSame($session->pdf_final_journal_path, $agendaA->journal_pdf_path);
        $this->assertSame($session->pdf_final_journal_path, $agendaB->journal_pdf_path);
        $this->assertDatabaseHas('legislative_session_final_journal_agenda_item', [
            'legislative_session_id' => $session->id,
            'agenda_item_id' => $agendaA->id,
        ]);
        $this->assertDatabaseHas('legislative_session_final_journal_agenda_item', [
            'legislative_session_id' => $session->id,
            'agenda_item_id' => $agendaB->id,
        ]);
    }

    public function test_untagging_clears_shared_journal_path_but_keeps_agenda_owned_path(): void
    {
        Storage::fake('local');

        [$admin, $session, $agendaA, $agendaB] = $this->seedSessionWithCommitteeReportAgendas();

        $sessionPath = 'order-of-business/'.$session->id.'/final-journal.pdf';
        Storage::disk('local')->put($sessionPath, '%PDF-1.4 session journal');
        $session->update(['pdf_final_journal_path' => $sessionPath]);

        $ownedPath = 'agenda/'.$agendaB->id.'/journal.pdf';
        Storage::disk('local')->put($ownedPath, '%PDF-1.4 agenda journal');

        $agendaA->update(['journal_pdf_path' => $sessionPath]);
        $agendaB->update(['journal_pdf_path' => $ownedPath]);

        $session->finalJournalAgendaItems()->sync([$agendaA->id, $agendaB->id]);

        $this->actingAs($admin)
            ->put(route('ob.sessions.update', $session), $this->sessionPayload($session, [
                'final_journal_agenda_ids' => [],
            ]))
            ->assertRedirect(route('ob.sessions.show', $session));

        $agendaA->refresh();
        $agendaB->refresh();
        $this->assertNull($agendaA->journal_pdf_path);
        $this->assertSame($ownedPath, $agendaB->journal_pdf_path);
        $this->assertDatabaseMissing('legislative_session_final_journal_agenda_item', [
            'legislative_session_id' => $session->id,
            'agenda_item_id' => $agendaA->id,
        ]);
        $this->assertDatabaseMissing('legislative_session_final_journal_agenda_item', [
            'legislative_session_id' => $session->id,
            'agenda_item_id' => $agendaB->id,
        ]);
    }

    /**
     * @return array{0: User, 1: LegislativeSession, 2: AgendaItem, 3: AgendaItem}
     */
    protected function seedSessionWithCommitteeReportAgendas(): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $session = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $document = ObDocument::create([
            'legislative_session_id' => $session->id,
            'title' => 'OB for final minutes tagging',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $agendaA = AgendaItem::create([
            'tracking_no' => 'FM-101',
            'title' => 'Committee report agenda A',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $admin->id,
        ]);
        $agendaB = AgendaItem::create([
            'tracking_no' => 'FM-102',
            'title' => 'Committee report agenda B',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $admin->id,
        ]);

        ObBlock::create([
            'ob_document_id' => $document->id,
            'type' => ObBlockType::CommitteeReport,
            'sort_order' => 1,
            'agenda_item_id' => $agendaA->id,
            'content' => [
                'committee_name' => 'Tourism',
                'agenda_item_ids' => [$agendaA->id],
            ],
        ]);
        ObBlock::create([
            'ob_document_id' => $document->id,
            'type' => ObBlockType::CommitteeReport,
            'sort_order' => 2,
            'agenda_item_id' => $agendaB->id,
            'content' => [
                'committee_name' => 'Health',
                'agenda_item_ids' => [$agendaB->id],
            ],
        ]);

        return [$admin, $session->fresh(['obDocument.blocks']), $agendaA, $agendaB];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function sessionPayload(LegislativeSession $session, array $extra = []): array
    {
        return array_merge([
            'session_date' => $session->session_date->format('Y-m-d'),
            'session_time' => $session->sessionTimeInputValue(),
            'session_number' => $session->session_number,
            'session_kind' => $session->session_kind,
            'venue' => $session->venue,
            'prior_session_id' => $session->prior_session_id,
            'status' => $session->status,
            'notes' => $session->notes,
            'pdf_committee_reports' => $session->pdf_committee_reports,
            'pdf_draft_journal' => $session->pdf_draft_journal,
            'pdf_draft_minutes' => $session->pdf_draft_minutes,
            'pdf_final_journal' => $session->pdf_final_journal,
            'pdf_final_minutes' => $session->pdf_final_minutes,
        ], $extra);
    }
}
