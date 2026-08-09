<?php

namespace Tests\Unit;

use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Services\AgendaLifecycleService;
use App\Services\ObDocumentService;
use Carbon\Carbon;
use Tests\TestCase;

class AgendaLifecycleServiceTest extends TestCase
{
    public function test_resolve_target_section_returns_unassigned_for_first_placement(): void
    {
        $service = new AgendaLifecycleService(
            $this->createMock(ObDocumentService::class),
        );

        $agenda = new AgendaItem([
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 30,
            'date_received' => now()->subDays(5),
        ]);

        $session = new LegislativeSession([
            'session_date' => now()->addWeek(),
            'status' => 'scheduled',
        ]);
        $session->id = 2;

        $this->assertSame('unassigned_regular', $service->resolveTargetSection($agenda, $session));
    }

    public function test_resolve_target_section_moves_to_unfinished_on_later_session(): void
    {
        $service = new AgendaLifecycleService(
            $this->createMock(ObDocumentService::class),
        );

        $priorSession = new LegislativeSession([
            'session_date' => Carbon::parse('2026-07-01'),
            'status' => 'scheduled',
        ]);
        $priorSession->id = 1;

        $nextSession = new LegislativeSession([
            'session_date' => Carbon::parse('2026-07-15'),
            'status' => 'scheduled',
        ]);
        $nextSession->id = 2;

        $agenda = new AgendaItem([
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'ob_lifecycle_stage' => AgendaItem::OB_STAGE_UNASSIGNED,
            'last_ob_synced_session_id' => $priorSession->id,
        ]);
        $agenda->setRelation('lastObSyncedSession', $priorSession);

        $this->assertSame('unfinished', $service->resolveTargetSection($agenda, $nextSession));
    }

    public function test_resolve_target_section_does_not_carry_committee_report_already_on_other_session(): void
    {
        $service = new AgendaLifecycleService(
            $this->createMock(ObDocumentService::class),
        );

        $priorSession = new LegislativeSession([
            'session_date' => Carbon::parse('2026-07-01'),
            'status' => 'done',
        ]);
        $priorSession->id = 1;

        $nextSession = new LegislativeSession([
            'session_date' => Carbon::parse('2026-07-15'),
            'status' => 'scheduled',
        ]);
        $nextSession->id = 2;

        $agenda = new AgendaItem([
            'committee_referred' => 'Tourism',
            'committee_report_url' => 'https://example.com/report.pdf',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'ob_lifecycle_stage' => AgendaItem::OB_STAGE_COMMITTEE_REPORT,
            'last_ob_synced_session_id' => $priorSession->id,
        ]);
        $agenda->id = 10;
        $agenda->setRelation('lastObSyncedSession', $priorSession);
        $agenda->setRelation('boardMemberCommitteeReports', collect());
        $agenda->setRelation('obPlacements', collect([
            new \App\Models\AgendaObPlacement([
                'agenda_item_id' => 10,
                'legislative_session_id' => 1,
                'section' => 'committee_reports',
            ]),
        ]));

        $this->assertNull($service->resolveTargetSection($agenda, $nextSession));
    }

    public function test_resolve_target_section_uses_committee_reports_when_pdf_path_exists_for_target_session(): void
    {
        $documentService = $this->createMock(ObDocumentService::class);
        $documentService->method('documentContainsAgenda')->willReturn(false);

        $service = new class($documentService) extends AgendaLifecycleService
        {
            public ?LegislativeSession $forcedTarget = null;

            public function resolveCommitteeReportTargetSession(AgendaItem $agenda): ?LegislativeSession
            {
                return $this->forcedTarget;
            }
        };

        $session = new LegislativeSession([
            'session_date' => now()->addWeek(),
            'status' => 'scheduled',
        ]);
        $session->id = 2;
        $service->forcedTarget = $session;

        $agenda = new AgendaItem([
            'committee_referred' => 'Tourism',
            'committee_report_pdf_path' => 'agenda-pdfs/1/committee-report.pdf',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
        ]);
        $agenda->id = 11;
        $agenda->setRelation('boardMemberCommitteeReports', collect());
        $agenda->setRelation('obPlacements', collect());

        $this->assertSame('committee_reports', $service->resolveTargetSection($agenda, $session));
    }

    public function test_resolve_target_section_keeps_committee_reports_when_prescription_days_elapsed(): void
    {
        $documentService = $this->createMock(ObDocumentService::class);
        $documentService->method('documentContainsAgenda')->willReturn(false);

        $service = new class($documentService) extends AgendaLifecycleService
        {
            public ?LegislativeSession $forcedTarget = null;

            public function resolveCommitteeReportTargetSession(AgendaItem $agenda): ?LegislativeSession
            {
                return $this->forcedTarget;
            }
        };

        $session = new LegislativeSession([
            'session_date' => now()->addWeek(),
            'status' => 'scheduled',
        ]);
        $session->id = 2;
        $service->forcedTarget = $session;

        $agenda = new AgendaItem([
            'committee_referred' => 'Tourism',
            'committee_report_pdf_path' => 'agenda-pdfs/1/committee-report.pdf',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 10,
            'date_received' => now()->subDays(40),
            'due_date' => now()->subDays(30),
            'days_left_label' => '-30',
        ]);
        $agenda->id = 12;
        $agenda->setRelation('boardMemberCommitteeReports', collect());
        $agenda->setRelation('obPlacements', collect());

        $this->assertFalse($service->prescribedDaysPermit($agenda));
        $this->assertSame('committee_reports', $service->resolveTargetSection($agenda, $session));
    }

    public function test_prescribed_days_permit_rejects_lapsed_agenda(): void
    {
        $service = new AgendaLifecycleService(
            $this->createMock(ObDocumentService::class),
        );

        $agenda = new AgendaItem([
            'status' => AgendaItem::STATUS_LAPSED,
            'prescribed_days' => 30,
        ]);

        $this->assertFalse($service->prescribedDaysPermit($agenda));
    }

    public function test_resolve_target_section_uses_urgent_unassigned_when_flagged(): void
    {
        $service = new AgendaLifecycleService(
            $this->createMock(ObDocumentService::class),
        );

        $agenda = new AgendaItem([
            'committee_referred' => 'Tourism',
            'is_urgent_request' => true,
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
        ]);

        $session = new LegislativeSession([
            'session_date' => now()->addWeek(),
            'status' => 'scheduled',
        ]);
        $session->id = 2;

        $this->assertSame('unassigned_urgent', $service->resolveTargetSection($agenda, $session));
    }

    public function test_is_session_after_compares_dates(): void
    {
        $service = new AgendaLifecycleService(
            $this->createMock(ObDocumentService::class),
        );

        $earlier = new LegislativeSession(['session_date' => Carbon::parse('2026-07-01')]);
        $earlier->id = 1;

        $later = new LegislativeSession(['session_date' => Carbon::parse('2026-07-15')]);
        $later->id = 2;

        $this->assertTrue($service->isSessionAfter($earlier, $later));
        $this->assertFalse($service->isSessionAfter($later, $earlier));
    }
}
