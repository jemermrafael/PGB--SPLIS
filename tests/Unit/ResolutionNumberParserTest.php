<?php

namespace Tests\Unit;

use App\Support\ResolutionNumberParser;
use PHPUnit\Framework\TestCase;

class ResolutionNumberParserTest extends TestCase
{
    public function test_extracts_sequence_from_modern_number(): void
    {
        $this->assertSame(450, ResolutionNumberParser::extractSequence('2022-B-0450'));
    }

    public function test_extracts_sequence_from_plain_number(): void
    {
        $this->assertSame(5, ResolutionNumberParser::extractSequence('05'));
    }

    public function test_parses_numeric_sp_res_no(): void
    {
        $parsed = ResolutionNumberParser::parseSpResNo('450');

        $this->assertSame('resolution', $parsed['kind']);
        $this->assertSame(450, $parsed['sequence']);
    }

    public function test_builds_official_number(): void
    {
        $this->assertSame('2026-B-0266', ResolutionNumberParser::buildOfficialNumber(2026, 266));
    }
}
