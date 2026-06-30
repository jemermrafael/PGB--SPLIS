<?php

namespace Tests\Unit;

use App\Support\SptrackRecordDatetime;
use PHPUnit\Framework\TestCase;

class SptrackRecordDatetimeTest extends TestCase
{
    public function test_it_parses_rec_added_with_trailing_username(): void
    {
        $parsed = SptrackRecordDatetime::parse('7/26/2017 9:50:45 AM May');

        $this->assertSame('2017-07-26 09:50:45', $parsed);
        $this->assertSame('May', SptrackRecordDatetime::extractUsername('7/26/2017 9:50:45 AM May'));
    }

    public function test_it_parses_rec_modified_with_trailing_username(): void
    {
        $parsed = SptrackRecordDatetime::parse('11/21/2017 3:30:00 PM May');

        $this->assertSame('2017-11-21 15:30:00', $parsed);
    }

    public function test_it_returns_null_for_empty_values(): void
    {
        $this->assertNull(SptrackRecordDatetime::parse(null));
        $this->assertNull(SptrackRecordDatetime::parse(''));
    }
}
