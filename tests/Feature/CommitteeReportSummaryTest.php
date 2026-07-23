<?php

namespace Tests\Feature;

use App\Enums\ObBlockType;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\CommitteeReportSummary;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Models\User;
use App\Services\CommitteeReportSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeReportSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_show_links_to_summary_maker(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $session = $this->makeSession($admin);

        $this->actingAs($admin)
            ->get(route('ob.sessions.show', $session))
            ->assertOk()
            ->assertSee('Summary of Comm. Reports')
            ->assertSee('Open Maker')
            ->assertSee(route('ob.sessions.committee-report-summary.maker', $session), false);
    }

    public function test_maker_seeds_groups_from_ob_committee_reports(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        [$session] = $this->seedCommitteeReportsOnSession($admin);

        $this->actingAs($admin)
            ->get(route('ob.sessions.committee-report-summary.maker', $session))
            ->assertOk()
            ->assertSee('Summary of Committee Reports Maker')
            ->assertSee('COMMITTEE ON FINANCE')
            ->assertSee('Agenda No. 501')
            ->assertSee('Supplemental Budget request')
            ->assertSee('RECOMMENDATION');

        $summary = CommitteeReportSummary::query()
            ->where('legislative_session_id', $session->id)
            ->first();

        $this->assertNotNull($summary);
        $groups = $summary->normalizedContent()['groups'];
        $this->assertCount(1, $groups);
        $this->assertSame('id:'.$this->lastAgendaId, app(CommitteeReportSummaryService::class)->itemKey($groups[0]['items'][0]));
    }

    public function test_user_can_save_recommendations_and_print_them(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        [$session, $agenda] = $this->seedCommitteeReportsOnSession($admin);

        $summary = app(CommitteeReportSummaryService::class)->ensureForSession($session, $admin->id);
        $itemKey = 'id:'.$agenda->id;

        $this->actingAs($admin)
            ->put(route('ob.sessions.committee-report-summary.update', $session), [
                'title' => 'SUMMARY OF COMMITTEE REPORT',
                'title_html' => '<mark><u><strong>SUMMARY OF COMMITTEE REPORT</strong></u></mark>',
                'report_date' => '2026-01-05',
                'prepared_by' => [
                    'name' => 'MARJORIE ANNE G. ORANI',
                    'title' => 'Board Secretary II',
                ],
                'reviewed_by' => [
                    'name' => 'MARY ANN R. DE JESUS, MPA',
                    'title' => "Prov'l Gov't Assistant Department Head",
                ],
                'bodies' => [
                    $itemKey => 'Supplemental Budget request',
                ],
                'bodies_html' => [
                    $itemKey => 'Supplemental Budget <strong>request</strong>',
                ],
                'recommendations' => [
                    $itemKey => 'TO APPROVE UPON SUSPENSION OF RULES',
                ],
                'recommendations_html' => [
                    $itemKey => '<u><mark>TO APPROVE</mark> UPON SUSPENSION OF RULES</u>',
                ],
            ])
            ->assertRedirect(route('ob.sessions.committee-report-summary.maker', $session));

        $summary->refresh();
        $item = $summary->normalizedContent()['groups'][0]['items'][0];
        $this->assertSame('TO APPROVE UPON SUSPENSION OF RULES', $item['recommendation'] ?? null);
        $this->assertSame('<u><mark>TO APPROVE</mark> UPON SUSPENSION OF RULES</u>', $item['recommendation_html'] ?? null);
        $this->assertSame('Supplemental Budget <strong>request</strong>', $item['body_html'] ?? null);

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->put(route('ob.sessions.committee-report-summary.update', $session), [
                'title' => 'SUMMARY OF COMMITTEE REPORT',
                'report_date' => '2026-01-05',
                'recommendations' => [
                    $itemKey => 'TO DECLARE OPERATIVE',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Saved');

        $summary->refresh();
        $this->assertSame(
            'TO DECLARE OPERATIVE',
            $summary->normalizedContent()['groups'][0]['items'][0]['recommendation'] ?? null,
        );

        $this->actingAs($admin)
            ->get(route('ob.sessions.committee-report-summary.print', $session))
            ->assertOk()
            ->assertSee('SUMMARY OF COMMITTEE REPORT')
            ->assertSee('JANUARY 5, 2026')
            ->assertSee('Agenda No. 501')
            ->assertSee('RECOMMENDATION:')
            ->assertSee('TO DECLARE OPERATIVE', false)
            ->assertDontSee('rowspan="2"', false)
            ->assertSee('Prepared by:')
            ->assertSee('MARJORIE ANNE G. ORANI')
            ->assertSee('Reviewed by:')
            ->assertSee('MARY ANN R. DE JESUS, MPA');
    }

    public function test_sync_preserves_existing_recommendations(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        [$session, $agenda] = $this->seedCommitteeReportsOnSession($admin);

        $service = app(CommitteeReportSummaryService::class);
        $summary = $service->ensureForSession($session, $admin->id);
        $service->update($summary, [
            'recommendations' => [
                'id:'.$agenda->id => 'TO DECLARE THE VALIDITY',
            ],
            'recommendations_html' => [
                'id:'.$agenda->id => '<mark>TO DECLARE THE VALIDITY</mark>',
            ],
        ]);

        $this->actingAs($admin)
            ->post(route('ob.sessions.committee-report-summary.sync', $session))
            ->assertRedirect(route('ob.sessions.committee-report-summary.maker', $session));

        $summary->refresh();
        $item = $summary->normalizedContent()['groups'][0]['items'][0];
        $this->assertSame('TO DECLARE THE VALIDITY', $item['recommendation'] ?? null);
        $this->assertSame('<mark>TO DECLARE THE VALIDITY</mark>', $item['recommendation_html'] ?? null);
    }

    protected int $lastAgendaId = 0;

    /**
     * @return array{0: LegislativeSession, 1: AgendaItem}
     */
    protected function seedCommitteeReportsOnSession(User $user): array
    {
        $session = $this->makeSession($user);

        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB',
            'status' => ObDocument::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        $agenda = AgendaItem::query()->create([
            'tracking_no' => '501',
            'title' => 'Supplemental Budget request',
            'committee_referred' => 'Finance, Budget, Appropriations and Ways and Means',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);
        $this->lastAgendaId = $agenda->id;

        ObBlock::query()->create([
            'ob_document_id' => $document->id,
            'type' => ObBlockType::CommitteeReport,
            'sort_order' => 40,
            'agenda_item_id' => $agenda->id,
            'content' => [
                'committee_name' => 'Finance, Budget, Appropriations and Ways and Means',
                'chair_name' => 'BM Jovy Z. Banzon',
                'agenda_no' => '501',
                'agenda_item_ids' => [$agenda->id],
            ],
        ]);

        return [$session->fresh(), $agenda];
    }

    protected function makeSession(User $user): LegislativeSession
    {
        return LegislativeSession::query()->create([
            'session_number' => '24',
            'session_kind' => 'regular',
            'session_date' => '2026-01-05',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
    }
}
