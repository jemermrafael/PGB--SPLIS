<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeMonitoringSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_json_payload_for_ajax_requests(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Encoder,
            'is_active' => true,
        ]);

        AgendaItem::create([
            'title' => 'Tourism referral',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        AgendaItem::create([
            'title' => 'Completed referral',
            'committee_referred' => 'Tourism',
            'outcome' => 'Approved',
            'status' => AgendaItem::STATUS_DONE,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('committee-monitoring.index', ['view' => 'pending']));

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.pending', 1)
            ->assertJsonPath('stats.completed', 1)
            ->assertJsonPath('filters.view', 'pending')
            ->assertJsonPath('data.0.title', 'Tourism referral');
    }

    public function test_index_still_renders_html_shell(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Encoder,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('committee-monitoring.index'))
            ->assertOk()
            ->assertSee('id="committee-monitoring"', false)
            ->assertSee('committee-monitoring-list-body', false);
    }
}
