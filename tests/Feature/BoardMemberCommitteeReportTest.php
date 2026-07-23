<?php

namespace Tests\Feature;

use App\Enums\CommitteeMembershipRole;
use App\Enums\ObBlockType;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\AgendaObPlacement;
use App\Models\BoardMember;
use App\Models\Committee;
use App\Models\CommitteeMembership;
use App\Models\CommitteeTerm;
use App\Models\LegislativeSession;
use App\Models\LegislativeSessionCommitteeReportFile;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Models\User;
use App\Services\ObDocumentTemplateService;
use App\Support\AgendaPdfSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BoardMemberCommitteeReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_member_can_submit_report_linked_to_agenda_and_session(): void
    {
        Storage::fake('local');

        [$user, $committee] = $this->linkedBoardMemberWithCommittee();

        $agenda = AgendaItem::query()->create([
            'tracking_no' => '401',
            'title' => 'Housing referral for report',
            'committee_referred' => $committee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $session = LegislativeSession::query()->create([
            'session_number' => '1',
            'session_kind' => 'regular',
            'session_date' => now()->toDateString(),
            'status' => 'scheduled',
            'created_by' => $user->id,
        ]);

        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB',
            'status' => ObDocument::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        app(ObDocumentTemplateService::class)->seedDefaultBlocks($document);

        $block = ObBlock::query()->create([
            'ob_document_id' => $document->id,
            'type' => ObBlockType::UnassignedAgenda,
            'sort_order' => 900,
            'content' => ['title' => $agenda->title, 'kind' => 'regular'],
            'agenda_item_id' => $agenda->id,
        ]);

        AgendaObPlacement::query()->create([
            'agenda_item_id' => $agenda->id,
            'ob_block_id' => $block->id,
            'ob_document_id' => $document->id,
            'legislative_session_id' => $session->id,
            'section' => 'unassigned_regular',
            'placed_by' => $user->id,
        ]);

        $pdf = UploadedFile::fake()->create('committee-report.pdf', 120, 'application/pdf');

        $this->actingAs($user)
            ->post(route('board-member.committee-reports.store'), [
                'title' => 'Housing Committee Report',
                'pdf' => $pdf,
                'agenda_item_ids' => [$agenda->id],
            ])
            ->assertRedirect(route('board-member.committee-reports.index'));

        $agenda->refresh();
        $this->assertNotNull($agenda->committee_report_pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($agenda->committee_report_pdf_path));
        $this->assertNotNull($agenda->pdfPublicUrlFor(AgendaPdfSlot::COMMITTEE_REPORT));
        $this->assertSame(AgendaItem::OB_STAGE_COMMITTEE_REPORT, $agenda->ob_lifecycle_stage);

        $this->assertDatabaseHas('board_member_committee_report_agenda_item', [
            'agenda_item_id' => $agenda->id,
        ]);

        $this->assertTrue(
            ObBlock::query()
                ->where('ob_document_id', $document->id)
                ->where('type', ObBlockType::CommitteeReport)
                ->where(function ($query) use ($agenda): void {
                    $query->where('agenda_item_id', $agenda->id)
                        ->orWhereJsonContains('content->agenda_item_ids', $agenda->id);
                })
                ->exists()
        );

        $this->assertFalse(
            ObBlock::query()
                ->whereKey($block->id)
                ->exists()
        );

        $sessionFile = LegislativeSessionCommitteeReportFile::query()
            ->where('legislative_session_id', $session->id)
            ->first();

        $this->assertNotNull($sessionFile);
        $this->assertTrue(Storage::disk('local')->exists($sessionFile->stored_path));
        $this->assertMatchesRegularExpression(
            '/^\d+\. HOUSING AND LAND USE-Agenda 401\.pdf$/',
            (string) $sessionFile->original_filename,
        );

        $report = \App\Models\BoardMemberCommitteeReport::query()->first();
        $this->assertNotNull($report);
        $this->assertSame($report->id, $sessionFile->board_member_committee_report_id);
        $this->assertSame($sessionFile->original_filename, $report->original_filename);
    }

    public function test_board_member_report_auto_places_agenda_into_committee_reports_section(): void
    {
        Storage::fake('local');

        [$user, $committee] = $this->linkedBoardMemberWithCommittee();

        $agenda = AgendaItem::query()->create([
            'tracking_no' => '402',
            'title' => 'Fresh agenda for committee report OB',
            'committee_referred' => $committee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $session = LegislativeSession::query()->create([
            'session_number' => '2',
            'session_kind' => 'regular',
            'session_date' => now()->addDay()->toDateString(),
            'status' => 'scheduled',
            'created_by' => $user->id,
        ]);

        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB upcoming',
            'status' => ObDocument::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        app(ObDocumentTemplateService::class)->seedDefaultBlocks($document);

        $pdf = UploadedFile::fake()->create('new-committee-report.pdf', 100, 'application/pdf');

        $this->actingAs($user)
            ->post(route('board-member.committee-reports.store'), [
                'title' => 'Auto OB Placement Report',
                'pdf' => $pdf,
                'agenda_item_ids' => [$agenda->id],
            ])
            ->assertRedirect(route('board-member.committee-reports.index'));

        $agenda->refresh();

        $this->assertNotNull($agenda->committee_report_pdf_path);
        $this->assertSame(AgendaItem::OB_STAGE_COMMITTEE_REPORT, $agenda->ob_lifecycle_stage);
        $this->assertSame($session->id, $agenda->last_ob_synced_session_id);

        $this->assertTrue(
            ObBlock::query()
                ->where('ob_document_id', $document->id)
                ->where('type', ObBlockType::CommitteeReport)
                ->where(function ($query) use ($agenda): void {
                    $query->where('agenda_item_id', $agenda->id)
                        ->orWhereJsonContains('content->agenda_item_ids', $agenda->id);
                })
                ->exists()
        );

        $this->assertDatabaseHas('agenda_ob_placements', [
            'agenda_item_id' => $agenda->id,
            'legislative_session_id' => $session->id,
            'section' => 'committee_reports',
        ]);

        $this->assertDatabaseHas('legislative_session_committee_report_files', [
            'legislative_session_id' => $session->id,
        ]);

        $sessionFile = LegislativeSessionCommitteeReportFile::query()
            ->where('legislative_session_id', $session->id)
            ->first();

        $this->assertNotNull($sessionFile);
        $this->assertMatchesRegularExpression(
            '/^\d+\. HOUSING AND LAND USE-Agenda 402\.pdf$/',
            (string) $sessionFile->original_filename,
        );
    }

    public function test_board_member_report_copies_pdf_to_tagged_agendas_and_ob_print_links(): void
    {
        Storage::fake('local');

        [$user, $committee] = $this->linkedBoardMemberWithCommittee();

        $agendaA = AgendaItem::query()->create([
            'tracking_no' => '058',
            'title' => 'First tagged agenda',
            'committee_referred' => $committee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);
        $agendaB = AgendaItem::query()->create([
            'tracking_no' => '267',
            'title' => 'Second tagged agenda',
            'committee_referred' => $committee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $session = LegislativeSession::query()->create([
            'session_number' => '4',
            'session_kind' => 'regular',
            'session_date' => now()->addDays(3)->toDateString(),
            'status' => 'scheduled',
            'created_by' => $user->id,
        ]);

        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB multi',
            'status' => ObDocument::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        app(ObDocumentTemplateService::class)->seedDefaultBlocks($document);

        $this->actingAs($user)
            ->post(route('board-member.committee-reports.store'), [
                'title' => 'Multi agenda report',
                'pdf' => UploadedFile::fake()->create('multi.pdf', 100, 'application/pdf'),
                'agenda_item_ids' => [$agendaA->id, $agendaB->id],
            ])
            ->assertRedirect(route('board-member.committee-reports.index'));

        $agendaA->refresh();
        $agendaB->refresh();
        $report = \App\Models\BoardMemberCommitteeReport::query()->firstOrFail();

        $this->assertSame($report->pdf_path, $agendaA->committee_report_pdf_path);
        $this->assertSame($report->pdf_path, $agendaB->committee_report_pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($report->pdf_path));
        $this->assertSame(
            '1. HOUSING AND LAND USE-Agenda 058, 267.pdf',
            $report->original_filename,
        );

        $sessionFile = LegislativeSessionCommitteeReportFile::query()
            ->where('legislative_session_id', $session->id)
            ->first();
        $this->assertNotNull($sessionFile);
        $this->assertSame('1. HOUSING AND LAND USE-Agenda 058, 267.pdf', $sessionFile->original_filename);

        $document->load('blocks.agendaItem');
        $segments = app(\App\Services\ObPrintRenderer::class)->segments($document->blocks, $session);
        $committeeSegment = collect($segments)->firstWhere('type', 'committee_reports_table');

        $this->assertNotNull($committeeSegment);
        $links = [];
        foreach ($committeeSegment['rows'] as $row) {
            foreach (($row['agenda_no_links'] ?? []) as $no => $url) {
                $links[(string) $no] = $url;
            }
        }

        $this->assertArrayHasKey('058', $links);
        $this->assertArrayHasKey('267', $links);
        $this->assertSame($links['058'], $links['267']);
        $this->assertSame($agendaA->pdfPublicUrlFor(AgendaPdfSlot::COMMITTEE_REPORT), $links['058']);
    }

    public function test_deleting_report_restores_agendas_to_previous_ob_section(): void
    {
        Storage::fake('local');

        [$user, $committee] = $this->linkedBoardMemberWithCommittee();

        $agenda = AgendaItem::query()->create([
            'tracking_no' => '058',
            'title' => 'Restore me',
            'committee_referred' => $committee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $session = LegislativeSession::query()->create([
            'session_number' => '5',
            'session_kind' => 'regular',
            'session_date' => now()->addDays(4)->toDateString(),
            'status' => 'scheduled',
            'created_by' => $user->id,
        ]);

        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB restore',
            'status' => ObDocument::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        app(ObDocumentTemplateService::class)->seedDefaultBlocks($document);

        app(\App\Services\ObDocumentService::class)->addAgendaItems(
            $document,
            [$agenda->id],
            null,
            'unfinished',
            null,
            $user->id,
            'manual',
        );

        $this->assertSame(
            'unfinished',
            app(\App\Services\ObDocumentService::class)->sectionForAgendaInDocument($document->fresh(), $agenda->id),
        );

        $this->actingAs($user)
            ->post(route('board-member.committee-reports.store'), [
                'pdf' => UploadedFile::fake()->create('restore.pdf', 80, 'application/pdf'),
                'agenda_item_ids' => [$agenda->id],
            ])
            ->assertRedirect(route('board-member.committee-reports.index'));

        $this->assertSame(
            'committee_reports',
            app(\App\Services\ObDocumentService::class)->sectionForAgendaInDocument($document->fresh(), $agenda->id),
        );

        $report = \App\Models\BoardMemberCommitteeReport::query()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('board-member.committee-reports.destroy', $report))
            ->assertRedirect(route('board-member.committee-reports.index'));

        $this->assertNull($agenda->fresh()->committee_report_pdf_path);
        $this->assertSame(
            'unfinished',
            app(\App\Services\ObDocumentService::class)->sectionForAgendaInDocument($document->fresh(), $agenda->id),
        );
    }

    public function test_board_member_can_replace_and_delete_submitted_report(): void
    {
        Storage::fake('local');

        [$user, $committee] = $this->linkedBoardMemberWithCommittee();

        $agenda = AgendaItem::query()->create([
            'tracking_no' => '58',
            'title' => 'Housing agenda for edit flow',
            'committee_referred' => $committee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $session = LegislativeSession::query()->create([
            'session_number' => '3',
            'session_kind' => 'regular',
            'session_date' => now()->addDays(2)->toDateString(),
            'status' => 'scheduled',
            'created_by' => $user->id,
        ]);

        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB edit',
            'status' => ObDocument::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        app(ObDocumentTemplateService::class)->seedDefaultBlocks($document);

        $this->actingAs($user)
            ->post(route('board-member.committee-reports.store'), [
                'title' => 'Housing Report',
                'pdf' => UploadedFile::fake()->create('housing.pdf', 90, 'application/pdf'),
                'agenda_item_ids' => [$agenda->id],
            ])
            ->assertRedirect(route('board-member.committee-reports.index'));

        $report = \App\Models\BoardMemberCommitteeReport::query()->firstOrFail();
        $this->assertSame('1. HOUSING AND LAND USE-Agenda 058.pdf', $report->original_filename);

        $this->actingAs($user)
            ->get(route('board-member.committee-reports.edit', $report))
            ->assertOk()
            ->assertSee('Cancel')
            ->assertSee('Replace PDF');

        $this->actingAs($user)
            ->put(route('board-member.committee-reports.update', $report), [
                'title' => 'Housing Report Updated',
                'pdf' => UploadedFile::fake()->create('housing-replaced.pdf', 110, 'application/pdf'),
                'agenda_item_ids' => [$agenda->id],
            ])
            ->assertRedirect(route('board-member.committee-reports.index'));

        $report->refresh();
        $this->assertSame('Housing Report Updated', $report->title);
        $this->assertSame('1. HOUSING AND LAND USE-Agenda 058.pdf', $report->original_filename);
        $this->assertNotNull($agenda->fresh()->committee_report_pdf_path);

        $this->actingAs($user)
            ->delete(route('board-member.committee-reports.destroy', $report))
            ->assertRedirect(route('board-member.committee-reports.index'));

        $this->assertDatabaseMissing('board_member_committee_reports', ['id' => $report->id]);
        $this->assertNull($agenda->fresh()->committee_report_pdf_path);
        $this->assertDatabaseMissing('legislative_session_committee_report_files', [
            'board_member_committee_report_id' => $report->id,
        ]);
    }

    public function test_board_member_cannot_tag_agenda_outside_their_committees(): void
    {
        Storage::fake('local');

        [$user] = $this->linkedBoardMemberWithCommittee();

        $otherAgenda = AgendaItem::query()->create([
            'tracking_no' => '999',
            'title' => 'Other committee item',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $pdf = UploadedFile::fake()->create('committee-report.pdf', 80, 'application/pdf');

        $this->actingAs($user)
            ->from(route('board-member.committee-reports.create'))
            ->post(route('board-member.committee-reports.store'), [
                'pdf' => $pdf,
                'agenda_item_ids' => [$otherAgenda->id],
            ])
            ->assertRedirect(route('board-member.committee-reports.create'))
            ->assertSessionHasErrors('agenda_item_ids');

        $this->assertNull($otherAgenda->fresh()->committee_report_pdf_path);
    }

    public function test_create_lists_only_chairmanship_agendas_without_reports(): void
    {
        [$user, $chairCommittee, $term, $boardMember] = $this->linkedBoardMemberWithCommittee();

        $memberOnlyCommittee = Committee::query()->create([
            'name' => 'Tourism',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        CommitteeMembership::query()->create([
            'committee_id' => $memberOnlyCommittee->id,
            'board_member_id' => $boardMember->id,
            'committee_term_id' => $term->id,
            'role' => CommitteeMembershipRole::Member,
            'sort_order' => 0,
        ]);

        $chairOpen = AgendaItem::query()->create([
            'tracking_no' => '101',
            'title' => 'Chair open agenda',
            'committee_referred' => $chairCommittee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);
        $chairWithReport = AgendaItem::query()->create([
            'tracking_no' => '102',
            'title' => 'Chair agenda with report',
            'committee_referred' => $chairCommittee->name,
            'committee_report_url' => 'https://example.com/report.pdf',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);
        $memberAgenda = AgendaItem::query()->create([
            'tracking_no' => '201',
            'title' => 'Member-only committee agenda',
            'committee_referred' => $memberOnlyCommittee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('board-member.committee-reports.create'))
            ->assertOk()
            ->assertSee('Chair open agenda')
            ->assertSee('All Chairmanships')
            ->assertSee($chairCommittee->name)
            ->assertDontSee('Chair agenda with report')
            ->assertDontSee('Member-only committee agenda')
            ->assertDontSee('Has report');

        $this->actingAs($user)
            ->get(route('board-member.committee-reports.create', [
                'committee_id' => $memberOnlyCommittee->id,
            ]))
            ->assertOk()
            ->assertSee('Chair open agenda')
            ->assertDontSee('Member-only committee agenda');

        $this->actingAs($user)
            ->get(route('board-member.committee-reports.create', [
                'committee_id' => $chairCommittee->id,
            ]))
            ->assertOk()
            ->assertSee('Chair open agenda')
            ->assertSee('selected')
            ->assertDontSee('Member-only committee agenda');

        $this->assertTrue(
            app(\App\Services\BoardMemberDashboardService::class)
                ->chairmanshipAgendasNeedingReportQueryFor($user)
                ->whereKey($chairOpen->id)
                ->exists()
        );
        $this->assertFalse(
            app(\App\Services\BoardMemberDashboardService::class)
                ->chairmanshipAgendasNeedingReportQueryFor($user)
                ->whereKey($chairWithReport->id)
                ->exists()
        );
        $this->assertFalse(
            app(\App\Services\BoardMemberDashboardService::class)
                ->chairmanshipAgendasNeedingReportQueryFor($user)
                ->whereKey($memberAgenda->id)
                ->exists()
        );

        $this->actingAs($user)
            ->getJson(route('board-member.committee-reports.agendas', [
                'committee_id' => $chairCommittee->id,
                'q' => 'open',
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Chair open agenda');
    }

    /**
     * @return array{0: User, 1: Committee, 2: CommitteeTerm, 3: BoardMember}
     */
    protected function linkedBoardMemberWithCommittee(): array
    {
        $term = CommitteeTerm::query()->create([
            'label' => '2025–2028',
            'year_from' => 2025,
            'year_to' => 2028,
            'is_current' => true,
        ]);

        $boardMember = BoardMember::query()->create([
            'name' => 'Linked Member',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);

        $committee = Committee::query()->create([
            'name' => 'Housing and Land Use',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        CommitteeMembership::query()->create([
            'committee_id' => $committee->id,
            'board_member_id' => $boardMember->id,
            'committee_term_id' => $term->id,
            'role' => CommitteeMembershipRole::Chair,
            'sort_order' => 0,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::BoardMember,
            'board_member_id' => $boardMember->id,
            'username' => 'bm_cr_'.uniqid(),
            'is_active' => true,
            'name' => 'Hon. Linked Member',
        ]);

        return [$user, $committee, $term, $boardMember];
    }
}
