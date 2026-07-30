<?php

namespace Tests\Unit;

use App\Support\DateRange;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DateRangeTest extends TestCase
{
    #[DataProvider('parseProvider')]
    public function test_parses_single_dates_and_ranges(string $input, ?string $start, ?string $end): void
    {
        $this->assertSame([$start, $end], DateRange::parse($input));
    }

    /**
     * @return array<string, array{0: string, 1: ?string, 2: ?string}>
     */
    public static function parseProvider(): array
    {
        return [
            'empty' => ['', null, null],
            'single' => ['4/1/2026', '2026-04-01', null],
            'inline range' => ['4/1/2026-4/7/2026', '2026-04-01', '2026-04-07'],
            'multiline range' => ["4/1/2026-\n4/7/2026", '2026-04-01', '2026-04-07'],
            'reversed order' => ['4/7/2026-4/1/2026', '2026-04-01', '2026-04-07'],
        ];
    }

    public function test_formats_display_ranges(): void
    {
        $this->assertSame(
            'April 1–7, 2026',
            DateRange::format(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-07')),
        );
        $this->assertSame(
            'April 1 – May 7, 2026',
            DateRange::format(Carbon::parse('2026-04-01'), Carbon::parse('2026-05-07')),
        );
        $this->assertSame(
            'April 1, 2026',
            DateRange::format(Carbon::parse('2026-04-01'), null),
        );
        $this->assertSame(
            'April 1, 2026',
            DateRange::format(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-01')),
        );
    }
}
