<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Municipality;
use App\Models\Ordinance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MunicipalOrdinanceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_municipal_viewer_can_open_ordinances_index_and_show(): void
    {
        $municipality = Municipality::query()->create([
            'code' => 101,
            'description' => 'Balanga',
        ]);

        $user = User::factory()->create([
            'role' => UserRole::MunicipalViewer,
            'municipality_id' => $municipality->id,
        ]);

        $ordinance = Ordinance::query()->create([
            'ordinance_no' => 20,
            'series_year' => 2026,
            'title' => 'Municipal visible title',
            'subject' => 'Subject',
        ]);

        $this->actingAs($user)
            ->get(route('ordinances.index'))
            ->assertOk()
            ->assertSee('Ordinances');

        $this->actingAs($user)
            ->get(route('ordinances.show', $ordinance))
            ->assertOk()
            ->assertSee('Municipal visible title');

        $this->actingAs($user)
            ->getJson(route('ordinances.search'))
            ->assertOk();
    }
}
