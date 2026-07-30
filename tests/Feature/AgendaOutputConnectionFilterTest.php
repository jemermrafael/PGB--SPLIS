<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\AppropriationOrdinance;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\User;
use App\Support\AgendaMeasureType;
use App\Support\DocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaOutputConnectionFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_sees_provincial_output_filter_on_agenda_index(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $this->actingAs($superadmin)
            ->get(route('agenda.index'))
            ->assertOk()
            ->assertSee('name="output_connection"', false)
            ->assertSee('Linked to Resolution', false)
            ->assertSee('Published to Ordinance', false);
    }

    public function test_non_superadmin_does_not_see_provincial_output_filter(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        $this->actingAs($encoder)
            ->get(route('agenda.index'))
            ->assertOk()
            ->assertDontSee('name="output_connection"', false);
    }

    public function test_superadmin_can_filter_agendas_by_output_connection(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $resolution = Resolution::query()->create([
            'resolution_no' => '100',
            'resolution_title' => 'Resolution output',
            'series' => 2026,
            'status' => 'approved',
            'document_type' => DocumentType::RESOLUTION,
            'created_by' => $superadmin->id,
        ]);
        $ordinance = Ordinance::query()->create([
            'ordinance_no' => '22',
            'title' => 'Ordinance output',
            'series' => 2026,
            'created_by' => $superadmin->id,
        ]);
        $appropriation = AppropriationOrdinance::query()->create([
            'subject' => 'Appropriation output',
            'ordinance_no' => 51,
            'series_year' => 2026,
            'created_by' => $superadmin->id,
        ]);

        $linkedResolution = AgendaItem::query()->create([
            'tracking_no' => '8001',
            'title' => 'Linked resolution agenda',
            'status' => AgendaItem::STATUS_DONE,
            'resolution_id' => $resolution->id,
            'reso_ord_ao_type' => AgendaMeasureType::RESOLUTION,
            'output_connection_type' => AgendaItem::OUTPUT_CONNECTION_LINKED,
            'published_at' => now(),
            'created_by' => $superadmin->id,
        ]);
        $publishedOrdinance = AgendaItem::query()->create([
            'tracking_no' => '8002',
            'title' => 'Published ordinance agenda',
            'status' => AgendaItem::STATUS_DONE,
            'ordinance_id' => $ordinance->id,
            'reso_ord_ao_type' => AgendaMeasureType::ORDINANCE,
            'output_connection_type' => AgendaItem::OUTPUT_CONNECTION_PUBLISHED,
            'published_at' => now(),
            'created_by' => $superadmin->id,
        ]);
        AgendaItem::query()->create([
            'tracking_no' => '8003',
            'title' => 'Linked appropriation agenda',
            'status' => AgendaItem::STATUS_DONE,
            'appropriation_ordinance_id' => $appropriation->id,
            'reso_ord_ao_type' => AgendaMeasureType::APPROPRIATION_ORDINANCE,
            'output_connection_type' => AgendaItem::OUTPUT_CONNECTION_LINKED,
            'published_at' => now(),
            'created_by' => $superadmin->id,
        ]);
        AgendaItem::query()->create([
            'tracking_no' => '8004',
            'title' => 'Unconnected agenda',
            'status' => AgendaItem::STATUS_PENDING,
            'created_by' => $superadmin->id,
        ]);

        $this->actingAs($superadmin)
            ->getJson(route('agenda.search', ['output_connection' => 'linked_resolution']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $linkedResolution->id);

        $this->actingAs($superadmin)
            ->getJson(route('agenda.search', ['output_connection' => 'published_ordinance']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $publishedOrdinance->id);

        $this->actingAs($superadmin)
            ->getJson(route('agenda.search', ['output_connection' => 'published']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $publishedOrdinance->id);

        $this->actingAs($superadmin)
            ->getJson(route('agenda.search', ['output_connection' => 'any']))
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $this->actingAs($superadmin)
            ->getJson(route('agenda.search', ['output_connection' => 'none']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.tracking_no', '8004');
    }

    public function test_non_superadmin_cannot_use_output_connection_filter(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        $resolution = Resolution::query()->create([
            'resolution_no' => '200',
            'resolution_title' => 'Resolution output',
            'series' => 2026,
            'status' => 'approved',
            'document_type' => DocumentType::RESOLUTION,
            'created_by' => $encoder->id,
        ]);

        AgendaItem::query()->create([
            'tracking_no' => '8101',
            'title' => 'Linked resolution agenda',
            'status' => AgendaItem::STATUS_DONE,
            'resolution_id' => $resolution->id,
            'reso_ord_ao_type' => AgendaMeasureType::RESOLUTION,
            'output_connection_type' => AgendaItem::OUTPUT_CONNECTION_LINKED,
            'published_at' => now(),
            'created_by' => $encoder->id,
        ]);
        AgendaItem::query()->create([
            'tracking_no' => '8102',
            'title' => 'Unconnected agenda',
            'status' => AgendaItem::STATUS_PENDING,
            'created_by' => $encoder->id,
        ]);

        $this->actingAs($encoder)
            ->getJson(route('agenda.search', ['output_connection' => 'linked_resolution']))
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }
}
