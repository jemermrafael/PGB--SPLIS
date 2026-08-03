<?php

namespace Tests\Feature;

use App\Enums\CommitteeMembershipRole;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\BoardMember;
use App\Models\Committee;
use App\Models\CommitteeMembership;
use App\Models\CommitteeTerm;
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
            ->assertSee('Overview of Legislative Operations and Performance')
            ->assertSee('Total Agenda Items')
            ->assertSee('Executive Heatmaps')
            ->assertSee('Legislative Output by Year')
            ->assertSee('Monthly Output')
            ->assertSee('Bataan — Agendas')
            ->assertSee('All months')
            ->assertSee('All Committees')
            ->assertDontSee('Geographic Dashboard')
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
            ]))
            ->assertOk()
            ->assertJsonPath('committee_id', $committee->id)
            ->assertJsonPath('period_label', '2024 (all months)')
            ->assertJsonStructure(['municipalities', 'total']);

        $this->actingAs($admin)
            ->getJson(route('admin.analytics.municipality-map', [
                'year' => 2024,
            ]))
            ->assertOk()
            ->assertJsonPath('committee', 'All Committees')
            ->assertJsonPath('committee_id', null);
    }

    public function test_geographic_dashboard_route_is_removed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get('/admin/analytics/geographic')
            ->assertNotFound();
    }

    public function test_unlinked_board_member_cannot_open_admin_analytics_page(): void
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

    public function test_board_member_dashboard_is_scoped_to_their_committees(): void
    {
        $term = CommitteeTerm::query()->create([
            'label' => '2025–2028',
            'year_from' => 2025,
            'year_to' => 2028,
            'is_current' => true,
        ]);

        $bm = BoardMember::query()->create([
            'name' => 'Scoped Member',
            'honorific' => 'Hon.',
            'district' => '1st District',
            'is_active' => true,
        ]);

        $mine = Committee::query()->create(['name' => 'Ways and Means Special', 'is_active' => true, 'sort_order' => 1]);
        $other = Committee::query()->create(['name' => 'Tourism Development Only', 'is_active' => true, 'sort_order' => 2]);

        CommitteeMembership::query()->create([
            'committee_id' => $mine->id,
            'board_member_id' => $bm->id,
            'committee_term_id' => $term->id,
            'role' => CommitteeMembershipRole::Chair,
            'sort_order' => 0,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::BoardMember,
            'board_member_id' => $bm->id,
            'is_active' => true,
        ]);

        $mineAgenda = AgendaItem::query()->create([
            'tracking_no' => '101',
            'title' => 'My committee agenda',
            'committee_referred' => $mine->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_received' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        AgendaItem::query()->create([
            'tracking_no' => '202',
            'title' => 'Other committee agenda',
            'committee_referred' => $other->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_received' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('Your committees, referred agendas, and related legislative output')
            ->assertSee('Ways and Means Special')
            ->assertSee('My Committees')
            ->assertDontSee('>All Committees</', false)
            ->assertSee('admin-analytics-data', false);

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/id="map-committee-id"[\s\S]*?Ways and Means Special/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="map-committee-id"[\s\S]*?Tourism Development Only[\s\S]*?<\/select>/', $html);

        $payload = app(\App\Services\ExecutiveAnalyticsService::class);
        $scope = $payload->resolveScope($user);
        $this->assertFalse($scope->isFull());
        $this->assertContains($mine->id, $scope->committeeIds());

        $kpis = $payload->usingScope($scope, fn () => $payload->kpis());
        $this->assertSame(1, (int) $kpis['total_agenda_items']);

        $this->actingAs($user)
            ->getJson(route('admin.analytics.municipality-map', [
                'committee_id' => $other->id,
                'year' => (int) now()->format('Y'),
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson(route('admin.analytics.municipality-map', [
                'year' => (int) now()->format('Y'),
            ]))
            ->assertOk()
            ->assertJsonPath('committee', 'My Committees');

        $this->assertTrue($mineAgenda->exists());
    }

    public function test_vice_governor_sees_full_executive_dashboard(): void
    {
        $term = CommitteeTerm::query()->create([
            'label' => '2025–2028',
            'year_from' => 2025,
            'year_to' => 2028,
            'is_current' => true,
        ]);

        $vg = BoardMember::query()->create([
            'name' => 'Vice Governor Person',
            'honorific' => 'Hon.',
            'district' => 'Vice Governor',
            'is_active' => true,
        ]);

        \App\Models\BoardMemberTerm::query()->create([
            'board_member_id' => $vg->id,
            'committee_term_id' => $term->id,
            'district' => 'Vice Governor',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $housing = Committee::query()->create(['name' => 'Housing and Land Use', 'is_active' => true]);
        $tourism = Committee::query()->create(['name' => 'Tourism', 'is_active' => true]);

        $user = User::factory()->create([
            'role' => UserRole::BoardMember,
            'board_member_id' => $vg->id,
            'is_active' => true,
        ]);

        AgendaItem::query()->create([
            'tracking_no' => '301',
            'title' => 'Province wide agenda',
            'committee_referred' => $tourism->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_received' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('Overview of Legislative Operations and Performance')
            ->assertSee('All Committees')
            ->assertSee($housing->name)
            ->assertSee($tourism->name);

        $this->assertTrue($user->seesFullExecutiveDashboard());
    }
}
