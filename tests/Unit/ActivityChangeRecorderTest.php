<?php

namespace Tests\Unit;

use App\Support\ActivityChangeRecorder;
use PHPUnit\Framework\TestCase;

class ActivityChangeRecorderTest extends TestCase
{
    public function test_it_detects_field_changes(): void
    {
        $changes = ActivityChangeRecorder::diff(
            ['title' => 'Old title', 'mun_series' => '2017'],
            ['title' => 'New title', 'mun_series' => '2017'],
            ['title', 'mun_series'],
        );

        $this->assertCount(1, $changes);
        $this->assertSame('Old title', $changes['title']['from']);
        $this->assertSame('New title', $changes['title']['to']);
    }

    public function test_it_treats_empty_values_as_equal(): void
    {
        $changes = ActivityChangeRecorder::diff(
            ['remarks' => null],
            ['remarks' => ''],
            ['remarks'],
        );

        $this->assertSame([], $changes);
    }
}
