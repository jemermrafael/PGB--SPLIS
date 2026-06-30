<?php

namespace Tests\Unit;

use App\Support\KeywordList;
use PHPUnit\Framework\TestCase;

class KeywordListTest extends TestCase
{
    public function test_it_splits_comma_separated_keywords_and_trims_commas(): void
    {
        $this->assertSame(
            ['appropriation', 'maintenance', 'flood control'],
            KeywordList::split('appropriation, maintenance, flood control'),
        );
    }

    public function test_it_strips_trailing_commas_from_each_part(): void
    {
        $this->assertSame(
            ['foo', 'bar'],
            KeywordList::split('foo,, bar,'),
        );
    }
}
