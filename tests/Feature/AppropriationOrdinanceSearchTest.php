<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AppropriationOrdinance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppropriationOrdinanceSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_matching_appropriation_ordinances(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        AppropriationOrdinance::query()->create([
            'subject' => 'Supplemental Budget No. 1',
            'ordinance_no' => 1,
            'series_year' => 2026,
            'created_by' => $user->id,
        ]);
        AppropriationOrdinance::query()->create([
            'subject' => 'Other appropriation',
            'ordinance_no' => 2,
            'series_year' => 2025,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('appropriation-ordinances.search', ['q' => '1']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.number', 'Appro. Ord. No. 01')
            ->assertJsonPath('data.0.subject', 'Supplemental Budget No. 1');

        $this->actingAs($user)
            ->getJson(route('appropriation-ordinances.search', ['series' => 2025]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.number', 'Appro. Ord. No. 02');
    }

    public function test_index_uses_ajax_shell_without_server_rendered_rows(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        AppropriationOrdinance::query()->create([
            'subject' => 'Supplemental Budget No. 1',
            'ordinance_no' => 1,
            'series_year' => 2026,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('appropriation-ordinances.index'))
            ->assertOk()
            ->assertSee('id="appropriation-ordinances-search"', false)
            ->assertSee('data-search-url="'.route('appropriation-ordinances.search').'"', false)
            ->assertSee('id="appropriation-ordinances-list-body"', false)
            ->assertDontSee('Supplemental Budget No. 1');
    }
}
