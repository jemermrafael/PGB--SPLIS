<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\AppropriationOrdinance;
use App\Models\Resolution;
use App\Models\User;
use App\Services\AgendaVersionService;
use App\Support\AgendaMeasureType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaOutputConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_output_identity_replaces_stale_connection_with_matching_resolution(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $oldAppropriation = AppropriationOrdinance::query()->create([
            'subject' => 'Old appropriation output',
            'ordinance_no' => 51,
            'series_year' => 2026,
        ]);
        $resolution = Resolution::query()->create([
            'resolution_no' => '2026-051',
            'resolution_title' => 'Existing resolution title',
            'series' => 2026,
            'status' => 'approved',
        ]);
        $agenda = AgendaItem::query()->create([
            'tracking_no' => '019',
            'status' => AgendaItem::STATUS_DONE,
            'title' => 'Municipal appropriation review',
            'reso_ord_ao_no' => 'Appro. Ord. No. 51',
            'reso_ord_ao_series' => 2026,
            'reso_ord_ao_type' => AgendaMeasureType::APPROPRIATION_ORDINANCE,
            'resolution_title' => 'Old output title',
            'appropriation_ordinance_id' => $oldAppropriation->id,
            'published_at' => now(),
            'output_connection_type' => AgendaItem::OUTPUT_CONNECTION_PUBLISHED,
            'created_by' => $user->id,
        ]);
        app(AgendaVersionService::class)->recordInitialVersion($agenda, $user->id);
        $oldAppropriation->update(['agenda_item_id' => $agenda->id]);

        $this->actingAs($user)
            ->put(route('agenda.update', $agenda), [
                'tracking_no' => '019',
                'status' => AgendaItem::STATUS_DONE,
                'title' => 'Municipal appropriation review',
                'reso_ord_ao_no' => '051',
                'reso_ord_ao_series' => 2026,
                'reso_ord_ao_type' => AgendaMeasureType::RESOLUTION,
                'resolution_title' => 'Resolution declaring the appropriation operative',
            ])
            ->assertRedirect(route('agenda.show', $agenda));

        $agenda->refresh();

        $this->assertSame($resolution->id, $agenda->resolution_id);
        $this->assertNull($agenda->appropriation_ordinance_id);
        $this->assertNull($oldAppropriation->fresh()->agenda_item_id);
        $this->assertSame(AgendaItem::OUTPUT_CONNECTION_LINKED, $agenda->output_connection_type);
        $this->assertSame('Existing resolution title', $resolution->fresh()->resolution_title);

        $this->actingAs($user)
            ->get(route('agenda.show', $agenda))
            ->assertOk()
            ->assertSee('Linked to Resolution No.:', false)
            ->assertDontSee('Published to Resolution No.:', false);
    }

    public function test_edit_publishes_new_output_when_no_match_exists(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $agenda = AgendaItem::query()->create([
            'tracking_no' => '020',
            'status' => AgendaItem::STATUS_PENDING,
            'title' => 'New resolution request',
            'created_by' => $user->id,
        ]);
        app(AgendaVersionService::class)->recordInitialVersion($agenda, $user->id);

        $this->actingAs($user)
            ->put(route('agenda.update', $agenda), [
                'tracking_no' => '020',
                'status' => AgendaItem::STATUS_DONE,
                'title' => 'New resolution request',
                'reso_ord_ao_no' => '999',
                'reso_ord_ao_series' => 2026,
                'reso_ord_ao_type' => AgendaMeasureType::RESOLUTION,
                'resolution_title' => 'A newly published resolution',
            ])
            ->assertRedirect(route('agenda.show', $agenda));

        $agenda->refresh();

        $this->assertNotNull($agenda->resolution_id);
        $this->assertSame(AgendaItem::OUTPUT_CONNECTION_PUBLISHED, $agenda->output_connection_type);

        $this->actingAs($user)
            ->get(route('agenda.show', $agenda))
            ->assertOk()
            ->assertSee('Published to Resolution No.:', false);
    }
}
