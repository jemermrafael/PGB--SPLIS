<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\ObDocument;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ObDocumentService;
use App\Support\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminActivityLogNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_agenda_created_notifies_active_admins(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $inactiveAdmin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => false]);

        $agenda = AgendaItem::create([
            'tracking_no' => 'TRK-001',
            'title' => 'Sample agenda',
            'sender' => 'Mariveles',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $encoder->id,
        ]);

        $this->actingAs($encoder);
        ActivityLogger::log('agenda.created', $agenda, [
            'tracking_no' => $agenda->tracking_no,
            'title' => $agenda->title,
            'sender' => $agenda->sender,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'type' => UserNotification::TYPE_ACTIVITY_LOG,
            'title' => 'Agenda created',
        ]);

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $inactiveAdmin->id,
            'type' => UserNotification::TYPE_ACTIVITY_LOG,
            'title' => 'Agenda created',
        ]);
    }

    public function test_agenda_published_notifies_active_admins(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder]);
        $admin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $agenda = AgendaItem::create([
            'tracking_no' => 'TRK-002',
            'title' => 'Published agenda',
            'status' => AgendaItem::STATUS_DONE,
            'prescribed_days' => 0,
            'reso_ord_ao_no' => '2026-15',
            'reso_ord_ao_series' => 2026,
            'created_by' => $encoder->id,
        ]);

        $this->actingAs($encoder);
        ActivityLogger::log('agenda.published', $agenda, [
            'target' => 'Resolution',
            'output_no' => $agenda->reso_ord_ao_no,
            'tracking_no' => $agenda->tracking_no,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'type' => UserNotification::TYPE_ACTIVITY_LOG,
            'title' => 'Agenda published',
        ]);
    }

    public function test_resolution_and_ordinance_created_notifies_active_admins(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $resolution = Resolution::create([
            'resolution_no' => '15',
            'resolution_title' => 'Direct resolution',
            'series' => 2026,
            'status' => 'draft',
            'created_by' => $encoder->id,
        ]);

        $ordinance = Ordinance::create([
            'ordinance_no' => 42,
            'series_year' => 2026,
            'subject' => 'Direct ordinance',
        ]);

        $this->actingAs($encoder);
        ActivityLogger::log('resolution.created', $resolution, ['resolution_no' => $resolution->resolution_no]);
        ActivityLogger::log('ordinance.created', $ordinance, [
            'ordinance_no' => $ordinance->ordinance_no,
            'series_year' => $ordinance->series_year,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'title' => 'Resolution created',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'title' => 'Ordinance created',
        ]);
    }

    public function test_added_to_ob_activity_does_not_notify_when_ob_is_draft(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        [$agenda, $session] = $this->agendaWithSessionOb(
            $encoder,
            sessionStatus: 'scheduled',
            obStatus: ObDocument::STATUS_DRAFT,
        );

        $this->actingAs($encoder);
        ActivityLogger::log('agenda.added_to_ob', $agenda, ActivityLogger::agendaObProperties($agenda, [
            'source' => 'automatic',
            'section' => 'committee_reports',
            'sections' => ['committee_reports', 'unfinished'],
            'section_label' => 'IV. Committee Reports',
            'section_labels' => ['IV. Committee Reports', 'A. Unfinished Business'],
            'session_id' => $session->id,
            'session_title' => $session->displayTitle(),
            'session_date' => $session->session_date?->format('Y-m-d'),
        ]));

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $admin->id,
            'type' => UserNotification::TYPE_ACTIVITY_LOG,
            'title' => 'Added to Order of Business',
        ]);
    }

    public function test_added_to_ob_activity_does_not_notify_per_agenda_when_ob_is_final(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        [$agenda, $session] = $this->agendaWithSessionOb(
            $encoder,
            sessionStatus: 'scheduled',
            obStatus: ObDocument::STATUS_FINAL,
        );

        $this->actingAs($encoder);
        ActivityLogger::log('agenda.added_to_ob', $agenda, ActivityLogger::agendaObProperties($agenda, [
            'source' => 'automatic',
            'section' => 'committee_reports',
            'sections' => ['committee_reports', 'unfinished'],
            'section_label' => 'IV. Committee Reports',
            'section_labels' => ['IV. Committee Reports', 'A. Unfinished Business'],
            'session_id' => $session->id,
            'session_title' => $session->displayTitle(),
            'session_date' => $session->session_date?->format('Y-m-d'),
        ]));

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $admin->id,
            'type' => UserNotification::TYPE_ACTIVITY_LOG,
            'title' => 'Added to Order of Business',
        ]);
    }

    public function test_ob_relocated_does_not_notify_admins(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        [$agenda, $session] = $this->agendaWithSessionOb(
            $encoder,
            sessionStatus: 'scheduled',
            obStatus: ObDocument::STATUS_FINAL,
        );

        $this->actingAs($encoder);
        ActivityLogger::log('agenda.ob_relocated', $agenda, ActivityLogger::agendaObProperties($agenda, [
            'from_section' => 'unfinished',
            'to_section' => 'committee_reports',
            'session_id' => $session->id,
            'session_title' => $session->displayTitle(),
        ]));

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $admin->id,
            'type' => UserNotification::TYPE_ACTIVITY_LOG,
        ]);
    }

    public function test_deferred_added_to_ob_activity_sends_one_finalize_digest(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        [$agenda, $session, $document] = $this->agendaWithSessionOb(
            $encoder,
            sessionStatus: 'scheduled',
            obStatus: ObDocument::STATUS_DRAFT,
            withDocument: true,
        );

        AgendaItem::create([
            'tracking_no' => '778',
            'title' => 'Second CR agenda',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'committee_report_pdf_path' => 'agenda-pdfs/778/committee-report.pdf',
            'created_by' => $encoder->id,
        ]);

        app(\App\Services\ObDocumentTemplateService::class)->seedDefaultBlocks($document);
        $agenda->update([
            'committee_report_pdf_path' => 'agenda-pdfs/777/committee-report.pdf',
        ]);
        app(\App\Services\AgendaLifecycleService::class)->syncNewSession($session->fresh('obDocument'), $encoder->id);

        $this->assertDatabaseMissing('activity_logs', [
            'action' => 'agenda.added_to_ob',
            'subject_id' => $agenda->id,
        ]);
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $admin->id,
            'title' => 'Added to Order of Business',
        ]);

        app(ObDocumentService::class)->updateDocument($document->fresh(), [
            'status' => ObDocument::STATUS_FINAL,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'agenda.added_to_ob',
            'subject_id' => $agenda->id,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'legislative_session_id' => $session->id,
            'type' => UserNotification::TYPE_OB_DOCUMENT_CREATED,
            'title' => \App\Services\ActivityLogNotifier::OB_FINALIZED_TITLE,
        ]);

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $admin->id,
            'type' => UserNotification::TYPE_ACTIVITY_LOG,
            'title' => \App\Services\ActivityLogNotifier::OB_FINALIZED_TITLE,
        ]);

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $admin->id,
            'title' => 'Added to Order of Business',
        ]);
    }

    public function test_subsequent_ob_adds_coalesce_into_one_admin_digest(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        [, $session] = $this->agendaWithSessionOb(
            $encoder,
            sessionStatus: 'scheduled',
            obStatus: ObDocument::STATUS_FINAL,
        );

        $notifier = app(\App\Services\ActivityLogNotifier::class);
        $notifier->digestSubsequentObAdds($session, 1);
        $notifier->digestSubsequentObAdds($session, 2);

        $digests = UserNotification::query()
            ->where('user_id', $admin->id)
            ->where('title', \App\Services\ActivityLogNotifier::OB_ADDED_DIGEST_TITLE)
            ->get();

        $this->assertCount(1, $digests);
        $this->assertStringContainsString('3 agendas added', (string) $digests->first()->body);
        $this->assertSame($session->id, $digests->first()->legislative_session_id);
    }

    /**
     * @return array{0: AgendaItem, 1: LegislativeSession, 2?: ObDocument}
     */
    protected function agendaWithSessionOb(
        User $encoder,
        string $sessionStatus,
        string $obStatus,
        bool $withDocument = false,
    ): array {
        $agenda = AgendaItem::create([
            'tracking_no' => '777',
            'title' => 'CR agenda',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $encoder->id,
        ]);

        $session = LegislativeSession::query()->create([
            'session_number' => '54th',
            'session_kind' => 'regular',
            'session_date' => now()->addDay()->toDateString(),
            'status' => $sessionStatus,
            'created_by' => $encoder->id,
        ]);

        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB',
            'status' => $obStatus,
            'created_by' => $encoder->id,
        ]);

        $session->setRelation('obDocument', $document);

        return $withDocument
            ? [$agenda, $session, $document]
            : [$agenda, $session];
    }
}
