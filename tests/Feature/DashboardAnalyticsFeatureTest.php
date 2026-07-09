<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAnalyticsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_dedicated_analytics_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('Data analytics command center')
            ->assertSee('Legislative output by year')
            ->assertSee('Committee ranking')
            ->assertSee('Referred')
            ->assertSee('Completed');
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
    }
}
