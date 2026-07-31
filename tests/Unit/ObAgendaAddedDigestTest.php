<?php

namespace Tests\Unit;

use App\Support\ObAgendaAddedDigest;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ObAgendaAddedDigestTest extends TestCase
{
    #[DataProvider('labelMentionProvider')]
    public function test_body_mentions_label(string $body, string $label, bool $expected): void
    {
        $this->assertSame($expected, ObAgendaAddedDigest::bodyMentionsLabel($body, $label));
    }

    /** @return array<string, array{0: string, 1: string, 2: bool}> */
    public static function labelMentionProvider(): array
    {
        $session = '53rd Regular Session';

        return [
            'exact single' => ["#349 was added to {$session}.", '#349', true],
            'in list first' => ["#349, #350 was added to {$session}.", '#349', true],
            'in list last' => ["#349, #350 was added to {$session}.", '#350', true],
            'does not match prefix of longer tracking no' => ["#10, #11 was added to {$session}.", '#1', false],
            'longer tracking no matches itself' => ["#10, #11 was added to {$session}.", '#10', true],
            'missing' => ["#349 was added to {$session}.", '#350', false],
        ];
    }
}
