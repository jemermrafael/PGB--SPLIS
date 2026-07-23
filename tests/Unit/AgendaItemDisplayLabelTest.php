<?php

namespace Tests\Unit;

use App\Models\AgendaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaItemDisplayLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbered_agenda_uses_tracking_number(): void
    {
        $agenda = AgendaItem::query()->create([
            'tracking_no' => '304',
            'is_urgent_request' => false,
            'status' => AgendaItem::STATUS_PENDING,
        ]);

        $this->assertSame('#304', $agenda->displayLabel());
        $this->assertSame('304', $agenda->listNumberLabel());
    }

    public function test_urgent_unnumbered_agenda_uses_dash_placeholder(): void
    {
        $agenda = AgendaItem::query()->create([
            'tracking_no' => null,
            'is_urgent_request' => true,
            'status' => AgendaItem::STATUS_PENDING,
        ]);

        $this->assertSame('---', $agenda->displayLabel());
        $this->assertSame('---', $agenda->listNumberLabel());
    }

    public function test_unnumbered_non_urgent_agenda_uses_unnumbered_placeholder(): void
    {
        $agenda = AgendaItem::query()->create([
            'tracking_no' => null,
            'is_urgent_request' => false,
            'status' => AgendaItem::STATUS_PENDING,
        ]);

        $this->assertSame('Unnumbered', $agenda->displayLabel());
        $this->assertSame('Unnumbered', $agenda->listNumberLabel());
    }

    public function test_effective_measure_type_uses_linked_resolution(): void
    {
        $agenda = new AgendaItem([
            'resolution_id' => 1,
            'reso_ord_ao_type' => null,
        ]);

        $this->assertSame('resolution', $agenda->effectiveMeasureType());
    }

    public function test_effective_measure_type_uses_linked_ordinance(): void
    {
        $agenda = new AgendaItem([
            'ordinance_id' => 1,
            'reso_ord_ao_type' => null,
        ]);

        $this->assertSame('ordinance', $agenda->effectiveMeasureType());
    }

    public function test_effective_measure_type_uses_linked_appropriation_ordinance(): void
    {
        $agenda = new AgendaItem([
            'appropriation_ordinance_id' => 1,
            'reso_ord_ao_type' => null,
        ]);

        $this->assertSame('appropriation_ordinance', $agenda->effectiveMeasureType());
    }

    public function test_stored_measure_type_wins_over_link(): void
    {
        $agenda = new AgendaItem([
            'resolution_id' => 1,
            'reso_ord_ao_type' => 'ordinance',
        ]);

        $this->assertSame('ordinance', $agenda->effectiveMeasureType());
    }

    public function test_provincial_output_number_uses_type_specific_label_and_series_format(): void
    {
        $agenda = new AgendaItem([
            'reso_ord_ao_type' => 'resolution',
            'reso_ord_ao_no' => '301',
            'reso_ord_ao_series' => 2026,
        ]);

        $this->assertSame('Resolution No.:', $agenda->provincialOutputNumberFieldLabel());
        $this->assertSame('2026-301', $agenda->provincialOutputNumberDisplay());
    }

    public function test_provincial_output_number_label_for_ordinance_and_ao(): void
    {
        $ordinance = new AgendaItem([
            'reso_ord_ao_type' => 'ordinance',
            'reso_ord_ao_no' => '12',
            'reso_ord_ao_series' => 2026,
        ]);
        $ao = new AgendaItem([
            'reso_ord_ao_type' => 'appropriation_ordinance',
            'reso_ord_ao_no' => '5',
            'reso_ord_ao_series' => 2026,
        ]);

        $this->assertSame('Ordinance No.:', $ordinance->provincialOutputNumberFieldLabel());
        $this->assertSame('2026-12', $ordinance->provincialOutputNumberDisplay());
        $this->assertSame('AO No.:', $ao->provincialOutputNumberFieldLabel());
        $this->assertSame('2026-5', $ao->provincialOutputNumberDisplay());
    }
}
