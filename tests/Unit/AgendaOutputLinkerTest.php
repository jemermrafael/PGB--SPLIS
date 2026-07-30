<?php

namespace Tests\Unit;

use App\Models\AgendaItem;
use App\Models\Resolution;
use App\Models\User;
use App\Services\AgendaOutputLinker;
use App\Support\AgendaMeasureType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaOutputLinkerTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_year_prefixed_resolution_from_bare_agenda_number(): void
    {
        $resolution = Resolution::query()->create([
            'resolution_no' => '2026-026',
            'resolution_title' => 'Sample resolution',
            'series' => 2026,
            'status' => 'approved',
        ]);

        $agenda = AgendaItem::query()->create([
            'tracking_no' => '008',
            'status' => AgendaItem::STATUS_DONE,
            'reso_ord_ao_no' => '026',
            'reso_ord_ao_series' => 2026,
            'reso_ord_ao_type' => AgendaMeasureType::RESOLUTION,
            'resolution_title' => 'Sample resolution',
        ]);

        $linker = app(AgendaOutputLinker::class);

        $this->assertTrue($linker->linkExistingIfPossible($agenda));
        $agenda->refresh();
        $this->assertSame($resolution->id, $agenda->resolution_id);
    }

    public function test_clears_dangling_resolution_link_and_relinks(): void
    {
        $alive = Resolution::query()->create([
            'resolution_no' => '2026-026',
            'resolution_title' => 'Alive',
            'series' => 2026,
            'status' => 'approved',
        ]);

        $dead = Resolution::query()->create([
            'resolution_no' => '026',
            'resolution_title' => 'Dead',
            'series' => 2026,
            'status' => 'approved',
        ]);
        $dead->delete();

        $agenda = AgendaItem::query()->create([
            'tracking_no' => '008',
            'status' => AgendaItem::STATUS_DONE,
            'reso_ord_ao_no' => '026',
            'reso_ord_ao_series' => 2026,
            'reso_ord_ao_type' => AgendaMeasureType::RESOLUTION,
            'resolution_id' => $dead->id,
            'published_at' => now(),
        ]);

        $linker = app(AgendaOutputLinker::class);
        $this->assertTrue($linker->linkExistingIfPossible($agenda));
        $agenda->refresh();
        $this->assertSame($alive->id, $agenda->resolution_id);
    }

    public function test_ordinance_style_number_cannot_be_linked_to_a_resolution(): void
    {
        $agenda = AgendaItem::query()->create([
            'title' => 'Rice subsidy program',
            'status' => AgendaItem::STATUS_DONE,
            'reso_ord_ao_no' => 'Ord. No. 22',
            'reso_ord_ao_series' => 2026,
        ]);

        $resolution = Resolution::query()->create([
            'resolution_no' => 'Ord. No. 22',
            'series' => 2026,
            'resolution_title' => 'Bogus resolution',
        ]);

        $this->expectException(\RuntimeException::class);

        app(AgendaOutputLinker::class)->linkManual($agenda, AgendaMeasureType::RESOLUTION, $resolution->id);
    }

    public function test_done_agenda_cannot_add_to_order_of_business_via_policy(): void
    {
        $user = User::factory()->create(['role' => 'encoder']);
        $agenda = new AgendaItem(['status' => AgendaItem::STATUS_DONE]);

        $this->assertFalse($user->can('addToOrderOfBusiness', $agenda));
    }
}
