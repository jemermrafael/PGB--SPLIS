<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\AppropriationOrdinance;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
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
