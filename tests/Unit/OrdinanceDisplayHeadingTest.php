<?php

namespace Tests\Unit;

use App\Models\Ordinance;
use Tests\TestCase;

class OrdinanceDisplayHeadingTest extends TestCase
{
    public function test_display_heading_includes_title_when_set(): void
    {
        $ordinance = new Ordinance([
            'ordinance_no' => 25,
            'series_year' => 2026,
            'title' => 'Eminent Domain for Road Right-of-Way to the Bataan Engineered Sanitary Landfill Facility',
        ]);

        $this->assertSame(
            'Ord. No. 25 - Eminent Domain for Road Right-of-Way to the Bataan Engineered Sanitary Landfill Facility',
            $ordinance->displayHeading()
        );
    }

    public function test_display_heading_falls_back_to_number_without_title(): void
    {
        $ordinance = new Ordinance([
            'ordinance_no' => 5,
            'series_year' => 2026,
            'title' => null,
        ]);

        $this->assertSame('Ord. No. 05', $ordinance->displayHeading());
    }

    public function test_list_title_prefers_title_over_subject(): void
    {
        $ordinance = new Ordinance([
            'title' => 'Short title',
            'subject' => 'Long legal subject text',
        ]);

        $this->assertSame('Short title', $ordinance->listTitle());
    }
}
