<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAnalyticsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_executive_overview_dashboard(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Committee::query()->create(['name' => 'Housing and Land Use', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('Executive Dashboard')
            ->assertSee('Total Agenda Items')
            ->assertSee('Executive Heatmaps')
            ->assertSee('Legislative Output by Year')
            ->assertSee('Monthly Output')
            ->assertSee('Bataan — Agendas')
            ->assertSee('All months')
            ->assertSee('All committees')
            ->assertDontSee('Geographic dashboard')
            ->assertDontSee('Department × Budget Amount')
            ->assertDontSee('Municipality × Resolution Category')
            ->assertDontSee('SLA Compliance')
            ->assertDontSee('Recent Legislative Activities')
            ->assertSee('admin-analytics-data', false);
    }

    public function test_admin_can_fetch_municipality_map_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $committee = Committee::query()->create([
            'name' => 'Finance, Budget, Appropriation, and Ways & Means',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.analytics.municipality-map', [
                'committee_id' => $committee->id,
                'year' => 2024,
                'measure' => 'both',
            ]))
            ->assertOk()
            ->assertJsonPath('committee_id', $committee->id)
            ->assertJsonPath('period_label', '2024 (all months)')
            ->assertJsonStructure(['municipalities', 'total', 'measure']);

        $this->actingAs($admin)
            ->getJson(route('admin.analytics.municipality-map', [
                'year' => 2024,
                'measure' => 'both',
            ]))
            ->assertOk()
            ->assertJsonPath('committee', 'All committees')
            ->assertJsonPath('committee_id', null);
    }

    public function test_geographic_dashboard_route_is_removed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get('/admin/analytics/geographic')
            ->assertNotFound();
    }

    public function test_board_member_cannot_open_admin_analytics_page(): void
    {
        $boardMember = User::factory()->create([
            'role' => UserRole::BoardMember,
            'board_member_id' => null,
        ]);

        $this->actingAs($boardMember)
            ->get(route('admin.analytics.index'))
            ->assertForbidden();

        $this->actingAs($boardMember)
            ->get(route('admin.analytics.municipality-map', ['committee_id' => 1, 'year' => 2024]))
            ->assertForbidden();
    }
}
