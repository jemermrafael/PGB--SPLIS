<?php

namespace Tests\Unit;

use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Services\AgendaLifecycleService;
use Carbon\Carbon;
use Tests\TestCase;

class AgendaLifecycleServiceTest extends TestCase
{
    public function test_resolve_target_section_returns_unassigned_for_first_placement(): void
    {
        $service = new AgendaLifecycleService(
            $this->createMock(\App\Services\ObDocumentService::class),
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
            $this->createMock(\App\Services\ObDocumentService::class),
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

    public function test_resolve_target_section_uses_committee_reports_when_report_exists(): void
    {
        $service = new AgendaLifecycleService(
            $this->createMock(\App\Services\ObDocumentService::class),
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
            'committee_report_url' => 'https://example.com/report.pdf',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'ob_lifecycle_stage' => AgendaItem::OB_STAGE_UNFINISHED,
            'last_ob_synced_session_id' => $priorSession->id,
        ]);
        $agenda->setRelation('lastObSyncedSession', $priorSession);

        $this->assertSame('committee_reports', $service->resolveTargetSection($agenda, $nextSession));
    }

    public function test_resolve_target_section_uses_committee_reports_when_pdf_path_exists(): void
    {
        $service = new AgendaLifecycleService(
            $this->createMock(\App\Services\ObDocumentService::class),
        );

        $agenda = new AgendaItem([
            'committee_referred' => 'Tourism',
            'committee_report_pdf_path' => 'agenda-pdfs/1/committee-report.pdf',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
        ]);

        $session = new LegislativeSession([
            'session_date' => now()->addWeek(),
            'status' => 'scheduled',
        ]);
        $session->id = 2;

        $this->assertSame('committee_reports', $service->resolveTargetSection($agenda, $session));
    }

    public function test_prescribed_days_permit_rejects_lapsed_agenda(): void
    {
        $service = new AgendaLifecycleService(
            $this->createMock(\App\Services\ObDocumentService::class),
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
            $this->createMock(\App\Services\ObDocumentService::class),
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
            $this->createMock(\App\Services\ObDocumentService::class),
        );

        $earlier = new LegislativeSession(['session_date' => Carbon::parse('2026-07-01')]);
        $earlier->id = 1;

        $later = new LegislativeSession(['session_date' => Carbon::parse('2026-07-15')]);
        $later->id = 2;

        $this->assertTrue($service->isSessionAfter($earlier, $later));
        $this->assertFalse($service->isSessionAfter($later, $earlier));
    }
}
