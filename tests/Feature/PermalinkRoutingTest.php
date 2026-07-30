<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\AppropriationOrdinance;
use App\Models\BoardMember;
use App\Models\LegislativeSession;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\User;
use App\Support\DocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermalinkRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_agenda_uses_year_and_tracking_no_permalink(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $agenda = AgendaItem::query()->create([
            'tracking_no' => '342',
            'title' => 'Permalink agenda',
            'status' => AgendaItem::STATUS_PENDING,
            'date_received' => '2026-07-23',
            'created_by' => $user->id,
        ]);

        $this->assertSame('2026-342', $agenda->getRouteKey());

        $this->actingAs($user)
            ->get('/agenda/'.$agenda->id)
            ->assertRedirect('/agenda/2026-342');

        $this->actingAs($user)
            ->get('/agenda/2026-342')
            ->assertOk()
            ->assertSee('Permalink agenda');
    }

    public function test_unnumbered_agenda_uses_year_u_and_database_id(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $agenda = AgendaItem::query()->create([
            'tracking_no' => null,
            'title' => 'Unnumbered urgent agenda',
            'status' => AgendaItem::STATUS_PENDING,
            'date_received' => '2026-01-26',
            'is_urgent_request' => true,
            'created_by' => $user->id,
        ]);

        $this->assertSame('2026-U-'.$agenda->id, $agenda->getRouteKey());

        $this->actingAs($user)
            ->get('/agenda/'.$agenda->id)
            ->assertRedirect('/agenda/2026-U-'.$agenda->id);

        $this->actingAs($user)
            ->get('/agenda/2026-U-'.$agenda->id)
            ->assertOk()
            ->assertSee('Unnumbered urgent agenda');
    }

    public function test_resolution_ordinance_and_ao_permalinks(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        $resolution = Resolution::query()->create([
            'resolution_no' => '2026-323',
            'resolution_title' => 'Permalink resolution',
            'series' => 2026,
            'status' => 'approved',
            'document_type' => DocumentType::RESOLUTION,
            'created_by' => $user->id,
        ]);
        $ordinance = Ordinance::query()->create([
            'ordinance_no' => 25,
            'series_year' => 2026,
            'title' => 'Permalink ordinance',
            'subject' => 'Subject',
            'created_by' => $user->id,
        ]);
        $ao = AppropriationOrdinance::query()->create([
            'ordinance_no' => 4,
            'series_year' => 2026,
            'subject' => 'Permalink AO',
            'created_by' => $user->id,
        ]);

        $this->assertSame('2026-323', $resolution->getRouteKey());
        $this->assertSame('2026-25', $ordinance->getRouteKey());
        $this->assertSame('2026-4', $ao->getRouteKey());

        $this->actingAs($user)->get('/resolutions/'.$resolution->id)->assertRedirect('/resolutions/2026-323');
        $this->actingAs($user)->get('/resolutions/2026-323')->assertOk();

        $this->actingAs($user)->get('/ordinances/'.$ordinance->id)->assertRedirect('/ordinances/2026-25');
        $this->actingAs($user)->get('/ordinances/2026-25')->assertOk();

        $this->actingAs($user)->get('/appropriation-ordinances/'.$ao->id)->assertRedirect('/appropriation-ordinances/2026-4');
        $this->actingAs($user)->get('/appropriation-ordinances/2026-4')->assertOk();
    }

    public function test_duplicate_resolution_numbers_append_legacy_sp_id(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        $first = Resolution::query()->create([
            'resolution_no' => '2005-A-0071',
            'resolution_title' => 'First duplicate',
            'series' => 2005,
            'legacy_sp_id' => 9001,
            'status' => 'approved',
            'document_type' => DocumentType::RESOLUTION,
            'created_by' => $user->id,
        ]);
        $second = Resolution::query()->create([
            'resolution_no' => '2005-A-0071',
            'resolution_title' => 'Second duplicate',
            'series' => 2005,
            'legacy_sp_id' => 9002,
            'status' => 'approved',
            'document_type' => DocumentType::RESOLUTION,
            'created_by' => $user->id,
        ]);

        $this->assertSame('2005-A-0071-9001', $first->getRouteKey());
        $this->assertSame('2005-A-0071-9002', $second->getRouteKey());

        $this->actingAs($user)
            ->get('/resolutions/2005-A-0071-9001')
            ->assertOk()
            ->assertSee('First duplicate');

        $this->actingAs($user)
            ->get('/resolutions/2005-A-0071-9002')
            ->assertOk()
            ->assertSee('Second duplicate');

        $this->actingAs($user)
            ->get('/resolutions/'.$first->id)
            ->assertRedirect('/resolutions/2005-A-0071-9001');
    }

    public function test_order_of_business_uses_year_and_ordinal_permalink(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $session = LegislativeSession::query()->create([
            'session_date' => '2026-07-27',
            'session_number' => '52ND REGULAR SESSION',
            'session_kind' => 'regular',
            'status' => 'scheduled',
            'created_by' => $user->id,
        ]);

        $this->assertSame('2026-52nd', $session->getRouteKey());

        $this->actingAs($user)
            ->get('/order-of-business/'.$session->id)
            ->assertRedirect('/order-of-business/2026-52nd');

        $this->actingAs($user)
            ->get('/order-of-business/2026-52nd')
            ->assertOk();
    }

    public function test_board_member_uses_name_slug_permalink_and_keeps_term_query(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $member = BoardMember::query()->create([
            'name' => 'Ma. Cristina M. Garcia',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);

        $this->assertSame('ma-cristina-m-garcia', $member->getRouteKey());

        $this->actingAs($user)
            ->get('/board-members/'.$member->id.'?term=2')
            ->assertRedirect('/board-members/ma-cristina-m-garcia?term=2');

        $this->actingAs($user)
            ->get('/board-members/ma-cristina-m-garcia')
            ->assertOk()
            ->assertSee('Ma. Cristina M. Garcia');
    }
}
