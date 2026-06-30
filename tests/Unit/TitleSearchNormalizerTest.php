<?php

namespace Tests\Unit;

use App\Support\TitleSearchNormalizer;
use PHPUnit\Framework\TestCase;

class TitleSearchNormalizerTest extends TestCase
{
    public function test_it_strips_punctuation_and_normalizes_whitespace(): void
    {
        $this->assertSame(
            'an act appropriating funds for maintenance',
            TitleSearchNormalizer::normalize('AN ACT, APPROPRIATING FUNDS FOR "MAINTENANCE".')
        );
    }

    public function test_it_returns_significant_tokens_sorted_by_length(): void
    {
        $tokens = TitleSearchNormalizer::significantTokens('AN ACT, APPROPRIATING FUNDS FOR MAINTENANCE');

        $this->assertContains('appropriating', $tokens);
        $this->assertContains('maintenance', $tokens);
        $this->assertNotContains('act', $tokens);
    }
}
