<?php

namespace Tests\Unit;

use App\Support\BataanPoliticalMap;
use Tests\TestCase;

class BataanPoliticalMapTest extends TestCase
{
    public function test_it_builds_twelve_municipality_paths_from_topojson(): void
    {
        $map = BataanPoliticalMap::svgPaths();

        $this->assertSame('0 0 1000 1000', $map['viewBox']);
        $this->assertCount(12, $map['paths']);
        $this->assertSame('balanga', BataanPoliticalMap::slugForName('City of Balanga'));
        $this->assertNotSame('', $map['paths'][0]['d'] ?? '');
    }
}
