<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\AppropriationOrdinance;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Services\ExecutiveAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_agenda_pipeline_and_output_by_year(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder]);

        AgendaItem::create([
            'title' => 'Pending one',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 30,
            'date_received' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        AgendaItem::create([
            'title' => 'Published one',
            'status' => AgendaItem::STATUS_DONE,
            'prescribed_days' => 0,
            'published_at' => now(),
            'created_by' => $user->id,
        ]);

        Resolution::create([
            'resolution_no' => '1',
            'resolution_title' => 'Test',
            'series' => 2026,
            'status' => 'approved',
            'created_by' => $user->id,
        ]);

        Ordinance::create([
            'ordinance_no' => 1,
            'series_year' => 2026,
            'subject' => 'Test ordinance',
        ]);

        AppropriationOrdinance::create([
            'ordinance_no' => 2,
            'series_year' => 2026,
            'subject' => 'Test appropriation ordinance',
        ]);

        $service = app(DashboardAnalyticsService::class);
        $pipeline = $service->agendaPipelineStats();
        $output = collect($service->outputByYear(3))->firstWhere('year', 2026);

        $this->assertSame(1, $pipeline['pending']);
        $this->assertSame(1, $pipeline['published']);
        $this->assertNotNull($output);
        $this->assertSame(1, $output['resolutions']);
        $this->assertSame(2, $output['ordinances']);
        $this->assertSame(3, $output['total']);
    }

    public function test_executive_kpis_and_sla(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder]);

        AgendaItem::create([
            'title' => 'Urgent pending',
            'status' => AgendaItem::STATUS_PENDING,
            'is_urgent_request' => true,
            'date_received' => now()->subDays(3)->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'prescribed_days' => 30,
            'created_by' => $user->id,
        ]);

        Resolution::create([
            'resolution_no' => '10',
            'resolution_title' => 'Budget measure',
            'series' => 2026,
            'status' => 'approved',
            'amount' => 2_350_000_000,
            'created_by' => $user->id,
        ]);

        $executive = app(ExecutiveAnalyticsService::class);
        $kpis = $executive->kpis();
        $sla = $executive->slaAnalytics();

        $this->assertSame(1, $kpis['total_agenda_items']);
        $this->assertSame(1, $kpis['urgent_requests']);
        $this->assertSame(1, $kpis['approved_resolutions']);
        $this->assertSame(2_350_000_000, $kpis['total_budget_approved']);
        $this->assertStringContainsString('Billion', $executive->formatBudget(2_350_000_000));
        $this->assertArrayHasKey('compliance_percent', $sla);
    }

    public function test_committee_municipality_map_uses_date_passed_and_date_approved(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder]);
        $committee = \App\Models\Committee::query()->create([
            'name' => 'Finance, Budget, Appropriation, and Ways & Means',
            'is_active' => true,
        ]);
        $justiceCommittee = \App\Models\Committee::query()->create([
            'name' => 'Justice, Human Rights, and Legal Matters',
            'is_active' => true,
        ]);
        $municipality = \App\Models\Municipality::query()->create([
            'code' => 'PIL',
            'description' => 'Pilar',
        ]);

        AgendaItem::query()->create([
            'sender' => 'Pilar',
            'committee_referred' => 'Finance, Budget, Appropriation, and Ways & Means',
            'date_received' => '2024-01-10',
            'date_passed' => '2024-06-15',
            'status' => AgendaItem::STATUS_DONE,
            'created_by' => $user->id,
        ]);

        AgendaItem::query()->create([
            'sender' => 'Pilar',
            'committee_referred' => 'Finance, Budget, Appropriation, and Ways & Means',
            'date_received' => '2024-06-15',
            'date_passed' => null,
            'status' => AgendaItem::STATUS_PENDING,
            'created_by' => $user->id,
        ]);

        AgendaItem::query()->create([
            'sender' => 'Pilar',
            'committee_referred' => 'Justice',
            'date_received' => '2024-02-01',
            'date_passed' => '2024-04-10',
            'status' => AgendaItem::STATUS_DONE,
            'created_by' => $user->id,
        ]);

        Resolution::query()->create([
            'resolution_no' => '101',
            'resolution_title' => 'Approved in period',
            'series' => 2024,
            'committee' => 'Finance, Budget, Appropriation, and Ways & Means',
            'municipality_id' => $municipality->id,
            'date_approved' => '2024-03-20',
            'status' => 'approved',
            'created_by' => $user->id,
        ]);

        Resolution::query()->create([
            'resolution_no' => '102',
            'resolution_title' => 'Not approved yet',
            'series' => 2024,
            'committee' => 'Finance, Budget, Appropriation, and Ways & Means',
            'municipality_id' => $municipality->id,
            'date_approved' => null,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        $map = app(ExecutiveAnalyticsService::class)->committeeMunicipalityMap($committee, 2024, null);
        $pilar = collect($map['municipalities'])->firstWhere('name', 'Pilar');

        $this->assertNotNull($pilar);
        $this->assertSame(1, $pilar['agendas']);
        $this->assertSame(1, $pilar['total']);

        $justiceMap = app(ExecutiveAnalyticsService::class)->committeeMunicipalityMap($justiceCommittee, 2024, null);
        $pilarJustice = collect($justiceMap['municipalities'])->firstWhere('name', 'Pilar');

        $this->assertNotNull($pilarJustice);
        $this->assertSame(1, $pilarJustice['agendas']);
    }

    public function test_legislative_output_counts_by_date_approved(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder]);

        Resolution::query()->create([
            'resolution_no' => '50',
            'resolution_title' => 'Approved 2024',
            'series' => 2023,
            'date_approved' => '2024-05-10',
            'status' => 'approved',
            'created_by' => $user->id,
        ]);

        $output = app(ExecutiveAnalyticsService::class)->legislativeOutputAnalytics(2024, 2024, 2024);
        $yearRow = collect($output['by_year'])->firstWhere('year', 2024);

        $this->assertNotNull($yearRow);
        $this->assertSame(1, $yearRow['resolutions']);
    }

    public function test_normalize_bar_chart_rows_sets_percentages(): void
    {
        $service = app(DashboardAnalyticsService::class);

        $rows = $service->normalizeBarChartRows([
            ['label' => '2024', 'total' => 10],
            ['label' => '2025', 'total' => 5],
            ['label' => '2026', 'total' => 20],
        ], 'label', 'total');

        $this->assertSame(100, $rows[2]['percent']);
        $this->assertSame(50, $rows[0]['percent']);
        $this->assertSame(25, $rows[1]['percent']);
    }
}
