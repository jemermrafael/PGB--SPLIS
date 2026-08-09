<?php

namespace Tests\Feature;

use App\Enums\ObBlockType;
use App\Models\AgendaItem;
use App\Models\BoardMember;
use App\Models\BoardMemberCommitteeReport;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Models\User;
use App\Services\AgendaLifecycleService;
use App\Services\ObDocumentTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaLifecycleSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_new_session_adds_first_time_agendas_as_unassigned(): void
    {
        $user = User::factory()->create();

        $agenda = AgendaItem::create([
            'title' => 'Test agenda',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $session = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $document = ObDocument::create([
            'legislative_session_id' => $session->id,
            'title' => 'OB',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        app(ObDocumentTemplateService::class)->seedDefaultBlocks($document);
        app(AgendaLifecycleService::class)->syncNewSession($session, $user->id);

        $agenda->refresh();

        $this->assertSame($session->id, $agenda->last_ob_synced_session_id);
        $this->assertTrue(
            ObBlock::query()
                ->where('ob_document_id', $document->id)
                ->where('agenda_item_id', $agenda->id)
                ->exists()
        );
    }

    public function test_sync_new_session_carries_agendas_to_unfinished_on_later_session(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(AgendaLifecycleService::class);

        $agenda = AgendaItem::create([
            'title' => 'Carry me',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $firstSession = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $firstDocument = ObDocument::create([
            'legislative_session_id' => $firstSession->id,
            'title' => 'OB 1',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        app(ObDocumentTemplateService::class)->seedDefaultBlocks($firstDocument);
        $lifecycle->syncNewSession($firstSession, $user->id);

        $secondSession = LegislativeSession::create([
            'session_date' => now()->addWeeks(2),
            'session_kind' => 'regular',
            'status' => 'draft',
            'prior_session_id' => $firstSession->id,
            'created_by' => $user->id,
        ]);

        $secondDocument = ObDocument::create([
            'legislative_session_id' => $secondSession->id,
            'title' => 'OB 2',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        app(ObDocumentTemplateService::class)->seedDefaultBlocks($secondDocument);
        $lifecycle->syncNewSession($secondSession, $user->id);

        $agenda->refresh();

        $this->assertSame($secondSession->id, $agenda->last_ob_synced_session_id);
        $this->assertTrue(
            ObBlock::query()
                ->where('ob_document_id', $secondDocument->id)
                ->where('agenda_item_id', $agenda->id)
                ->exists()
        );
    }

    public function test_sync_sorts_agendas_by_number_in_their_section(): void
    {
        $user = User::factory()->create();

        foreach (['300', '100', '200'] as $agendaNo) {
            AgendaItem::create([
                'tracking_no' => $agendaNo,
                'title' => 'Agenda '.$agendaNo,
                'committee_referred' => 'Tourism',
                'status' => AgendaItem::STATUS_PENDING,
                'prescribed_days' => 0,
                'date_received' => '2026-01-15',
                'created_by' => $user->id,
            ]);
        }

        $session = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $document = ObDocument::create([
            'legislative_session_id' => $session->id,
            'title' => 'Sorted OB',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        app(ObDocumentTemplateService::class)->seedDefaultBlocks($document);

        app(AgendaLifecycleService::class)->syncNewSession($session, $user->id);

        $this->assertSame(
            ['100', '200', '300'],
            ObBlock::query()
                ->where('ob_document_id', $document->id)
                ->where('type', ObBlockType::UnassignedAgenda)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (ObBlock $block) => (string) $block->content['agenda_no'])
                ->all(),
        );
    }

    public function test_sync_sorts_agendas_by_year_then_number(): void
    {
        $user = User::factory()->create();

        AgendaItem::create([
            'tracking_no' => '113',
            'title' => 'Newer lower number',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'date_received' => '2026-03-01',
            'created_by' => $user->id,
        ]);
        AgendaItem::create([
            'tracking_no' => '580',
            'title' => 'Older higher number',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'date_received' => '2023-06-15',
            'created_by' => $user->id,
        ]);
        AgendaItem::create([
            'tracking_no' => '50',
            'title' => 'Same year earlier number',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'date_received' => '2023-01-10',
            'created_by' => $user->id,
        ]);

        $session = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $document = ObDocument::create([
            'legislative_session_id' => $session->id,
            'title' => 'Year sorted OB',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        app(ObDocumentTemplateService::class)->seedDefaultBlocks($document);

        app(AgendaLifecycleService::class)->syncNewSession($session, $user->id);

        $this->assertSame(
            ['50', '580', '113'],
            ObBlock::query()
                ->where('ob_document_id', $document->id)
                ->where('type', ObBlockType::UnassignedAgenda)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (ObBlock $block) => (string) $block->content['agenda_no'])
                ->all(),
        );
    }

    public function test_sync_places_overdue_agenda_with_committee_report_into_committee_reports(): void
    {
        $user = User::factory()->create();

        $agenda = AgendaItem::create([
            'tracking_no' => '505',
            'title' => 'Overdue report-backed agenda',
            'committee_referred' => 'Tourism',
            'committee_report_pdf_path' => 'agenda-pdfs/505/committee-report.pdf',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 10,
            'date_received' => now()->subDays(40)->toDateString(),
            'due_date' => now()->subDays(30)->toDateString(),
            'days_left_label' => '-30',
            'created_by' => $user->id,
        ]);

        $session = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $document = ObDocument::create([
            'legislative_session_id' => $session->id,
            'title' => 'Overdue CR OB',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        app(ObDocumentTemplateService::class)->seedDefaultBlocks($document);

        app(AgendaLifecycleService::class)->syncNewSession($session, $user->id);

        $this->assertDatabaseHas('ob_blocks', [
            'ob_document_id' => $document->id,
            'type' => ObBlockType::CommitteeReport,
            'agenda_item_id' => $agenda->id,
        ]);
    }

    public function test_committee_report_agenda_is_not_carried_to_later_session_ob(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(AgendaLifecycleService::class);

        $agenda = AgendaItem::create([
            'tracking_no' => '777',
            'title' => 'CR once only',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $firstSession = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $firstDocument = ObDocument::create([
            'legislative_session_id' => $firstSession->id,
            'title' => 'OB 1',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        app(ObDocumentTemplateService::class)->seedDefaultBlocks($firstDocument);
        $lifecycle->syncNewSession($firstSession, $user->id);

        $agenda->update([
            'committee_report_pdf_path' => 'agenda-pdfs/777/committee-report.pdf',
        ]);
        $lifecycle->handleAgendaSaved($agenda->fresh(['boardMemberCommitteeReports', 'obPlacements']), [
            'committee_report_pdf_path',
        ], $user->id);

        $documentService = app(\App\Services\ObDocumentService::class);
        $firstSections = $documentService->sectionsForAgendaInDocument($firstDocument->fresh(), $agenda->id);
        $this->assertTrue($firstSections->contains('committee_reports'));

        $secondSession = LegislativeSession::create([
            'session_date' => now()->addWeeks(2),
            'session_kind' => 'regular',
            'status' => 'draft',
            'prior_session_id' => $firstSession->id,
            'created_by' => $user->id,
        ]);
        $secondDocument = ObDocument::create([
            'legislative_session_id' => $secondSession->id,
            'title' => 'OB 2',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        app(ObDocumentTemplateService::class)->seedDefaultBlocks($secondDocument);
        $lifecycle->syncNewSession($secondSession, $user->id);

        $sections = $documentService->sectionsForAgendaInDocument($secondDocument->fresh(), $agenda->id);

        $this->assertFalse($sections->contains('committee_reports'));
        $this->assertFalse($sections->contains('unfinished'));
        $this->assertDatabaseMissing('ob_blocks', [
            'ob_document_id' => $secondDocument->id,
            'agenda_item_id' => $agenda->id,
        ]);
    }

    public function test_filing_committee_report_keeps_existing_unfinished_placement(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(AgendaLifecycleService::class);
        $documentService = app(\App\Services\ObDocumentService::class);

        $agenda = AgendaItem::create([
            'tracking_no' => '888',
            'title' => 'Unfinished then CR',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $firstSession = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $firstDocument = ObDocument::create([
            'legislative_session_id' => $firstSession->id,
            'title' => 'OB 1',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        app(ObDocumentTemplateService::class)->seedDefaultBlocks($firstDocument);
        $lifecycle->syncNewSession($firstSession, $user->id);
        $firstSession->update(['status' => 'completed']);

        $secondSession = LegislativeSession::create([
            'session_date' => now()->addWeeks(2),
            'session_kind' => 'regular',
            'status' => 'draft',
            'prior_session_id' => $firstSession->id,
            'created_by' => $user->id,
        ]);
        $secondDocument = ObDocument::create([
            'legislative_session_id' => $secondSession->id,
            'title' => 'OB 2',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        app(ObDocumentTemplateService::class)->seedDefaultBlocks($secondDocument);
        $lifecycle->syncNewSession($secondSession, $user->id);

        $this->assertTrue($documentService->agendaIsInSection($secondDocument->fresh(), $agenda->id, 'unfinished'));

        $agenda->update([
            'committee_report_pdf_path' => 'agenda-pdfs/888/committee-report.pdf',
        ]);
        $lifecycle->handleAgendaSaved($agenda->fresh(['boardMemberCommitteeReports']), [
            'committee_report_pdf_path',
        ], $user->id);

        $sections = $documentService->sectionsForAgendaInDocument($secondDocument->fresh(), $agenda->id);
        $this->assertTrue($sections->contains('unfinished'), 'sections: '.$sections->implode(','));
        $this->assertTrue($sections->contains('committee_reports'), 'sections: '.$sections->implode(','));
    }

    public function test_first_session_committee_report_agenda_also_placed_in_unfinished(): void
    {
        $user = User::factory()->create();

        $agenda = AgendaItem::create([
            'tracking_no' => '901',
            'title' => 'CR on first session',
            'committee_referred' => 'Tourism',
            'committee_report_pdf_path' => 'agenda-pdfs/901/committee-report.pdf',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $session = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $document = ObDocument::create([
            'legislative_session_id' => $session->id,
            'title' => 'CR first OB',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        app(ObDocumentTemplateService::class)->seedDefaultBlocks($document);

        app(AgendaLifecycleService::class)->syncNewSession($session, $user->id);

        $sections = app(\App\Services\ObDocumentService::class)
            ->sectionsForAgendaInDocument($document->fresh(), $agenda->id);

        $this->assertTrue($sections->contains('committee_reports'));
        $this->assertTrue($sections->contains('unfinished'));

        $this->assertSame(0, $agenda->activityLogs()
            ->where('action', 'agenda.added_to_ob')
            ->count());

        app(\App\Services\ObDocumentService::class)->updateDocument($document, [
            'status' => ObDocument::STATUS_FINAL,
        ]);

        $addedLogs = $agenda->activityLogs()
            ->where('action', 'agenda.added_to_ob')
            ->get();

        $this->assertCount(1, $addedLogs);
        $this->assertSame(
            ['committee_reports', 'unfinished'],
            $addedLogs->first()->properties['sections'] ?? null,
        );
    }

    public function test_sync_recovers_agenda_linked_to_board_member_report_even_when_legacy_agenda_fields_are_empty(): void
    {
        $user = User::factory()->create();
        $boardMember = BoardMember::query()->create([
            'name' => 'Committee Chair',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);
        $agenda = AgendaItem::create([
            'tracking_no' => '404',
            'title' => 'Report-backed agenda',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);
        $report = BoardMemberCommitteeReport::query()->create([
            'board_member_id' => $boardMember->id,
            'title' => 'Tourism report',
            'pdf_path' => 'board-member-committee-reports/report.pdf',
            'original_filename' => 'report.pdf',
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);
        $report->agendaItems()->attach($agenda->id);

        $session = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $document = ObDocument::create([
            'legislative_session_id' => $session->id,
            'title' => 'Recovered OB',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        app(ObDocumentTemplateService::class)->seedDefaultBlocks($document);

        app(AgendaLifecycleService::class)->syncNewSession($session, $user->id);

        $this->assertDatabaseHas('ob_blocks', [
            'ob_document_id' => $document->id,
            'type' => ObBlockType::CommitteeReport,
            'agenda_item_id' => $agenda->id,
        ]);
    }

    public function test_reserved_committee_report_places_when_next_session_ob_is_created(): void
    {
        $user = User::factory()->create();
        $boardMember = BoardMember::query()->create([
            'name' => 'Chair',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);
        $agenda = AgendaItem::create([
            'tracking_no' => '910',
            'title' => 'Reserved CR agenda',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
            'committee_report_pdf_path' => 'board-member-committee-reports/reserved.pdf',
        ]);
        $report = BoardMemberCommitteeReport::query()->create([
            'board_member_id' => $boardMember->id,
            'legislative_session_id' => null,
            'title' => 'Reserved report',
            'pdf_path' => 'board-member-committee-reports/reserved.pdf',
            'original_filename' => 'reserved.pdf',
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);
        $report->agendaItems()->attach($agenda->id);

        $lifecycle = app(AgendaLifecycleService::class);
        $lifecycle->handleAgendaSaved($agenda->fresh(['boardMemberCommitteeReports', 'obPlacements']), [
            'committee_report_pdf_path',
        ], $user->id);

        $this->assertDatabaseMissing('ob_blocks', [
            'agenda_item_id' => $agenda->id,
            'type' => ObBlockType::CommitteeReport,
        ]);

        $session = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $document = ObDocument::create([
            'legislative_session_id' => $session->id,
            'title' => 'Next OB',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        app(ObDocumentTemplateService::class)->seedDefaultBlocks($document);
        $lifecycle->syncNewSession($session, $user->id);

        $this->assertDatabaseHas('ob_blocks', [
            'ob_document_id' => $document->id,
            'type' => ObBlockType::CommitteeReport,
            'agenda_item_id' => $agenda->id,
        ]);
    }

    public function test_committee_report_with_explicit_session_targets_that_session_only(): void
    {
        $user = User::factory()->create();
        $boardMember = BoardMember::query()->create([
            'name' => 'Chair',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);
        $agenda = AgendaItem::create([
            'tracking_no' => '911',
            'title' => 'Explicit session CR',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
            'committee_report_pdf_path' => 'board-member-committee-reports/explicit.pdf',
        ]);

        $firstSession = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $firstDocument = ObDocument::create([
            'legislative_session_id' => $firstSession->id,
            'title' => 'OB 1',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        app(ObDocumentTemplateService::class)->seedDefaultBlocks($firstDocument);

        $secondSession = LegislativeSession::create([
            'session_date' => now()->addWeeks(2),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $secondDocument = ObDocument::create([
            'legislative_session_id' => $secondSession->id,
            'title' => 'OB 2',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        app(ObDocumentTemplateService::class)->seedDefaultBlocks($secondDocument);

        $report = BoardMemberCommitteeReport::query()->create([
            'board_member_id' => $boardMember->id,
            'legislative_session_id' => $secondSession->id,
            'title' => 'Explicit report',
            'pdf_path' => 'board-member-committee-reports/explicit.pdf',
            'original_filename' => 'explicit.pdf',
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);
        $report->agendaItems()->attach($agenda->id);

        $lifecycle = app(AgendaLifecycleService::class);
        $lifecycle->handleAgendaSaved($agenda->fresh(['boardMemberCommitteeReports', 'obPlacements']), [
            'committee_report_pdf_path',
        ], $user->id);

        $this->assertDatabaseMissing('ob_blocks', [
            'ob_document_id' => $firstDocument->id,
            'agenda_item_id' => $agenda->id,
            'type' => ObBlockType::CommitteeReport,
        ]);
        $this->assertDatabaseHas('ob_blocks', [
            'ob_document_id' => $secondDocument->id,
            'agenda_item_id' => $agenda->id,
            'type' => ObBlockType::CommitteeReport,
        ]);

        $lifecycle->syncNewSession($firstSession, $user->id);

        $this->assertDatabaseMissing('ob_blocks', [
            'ob_document_id' => $firstDocument->id,
            'agenda_item_id' => $agenda->id,
            'type' => ObBlockType::CommitteeReport,
        ]);
    }
}
