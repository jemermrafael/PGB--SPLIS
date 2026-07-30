<?php

namespace Tests\Unit;

use App\Support\Permalink;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PermalinkTest extends TestCase
{
    #[DataProvider('ordinals')]
    public function test_ordinal_from_session_number(string $input, string $expected): void
    {
        $this->assertSame($expected, Permalink::ordinalFromSessionNumber($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function ordinals(): array
    {
        return [
            '52nd' => ['52ND REGULAR SESSION', '52nd'],
            '1st' => ['1st Regular Session', '1st'],
            'numeric' => ['3', '3rd'],
        ];
    }

    public function test_board_member_slug(): void
    {
        $this->assertSame(
            'ma-cristina-m-garcia',
            Permalink::boardMemberSlug('Ma. Cristina M. Garcia'),
        );
    }

    public function test_agenda_key_formats(): void
    {
        $this->assertSame('2026-342', Permalink::agendaKey(2026, '342', 383));
        $this->assertSame('2026-U-383', Permalink::agendaKey(2026, null, 383));
        $this->assertSame('2026-U-383', Permalink::agendaKey(2026, '', 383));

        $this->assertSame(
            ['year' => 2026, 'tracking_no' => '342'],
            Permalink::parseAgendaKey('2026-342'),
        );
        $this->assertSame(
            ['year' => 2026, 'unnumbered_id' => 383],
            Permalink::parseAgendaKey('2026-U-383'),
        );
    }

    public function test_resolution_duplicate_key_formats(): void
    {
        $this->assertSame(
            '2005-A-0071-9001',
            Permalink::resolutionDuplicateKey('2005-A-0071', 9001),
        );
        $this->assertSame(
            ['resolution_no' => '2005-A-0071', 'legacy_sp_id' => 9001],
            Permalink::parseResolutionDuplicateKey('2005-A-0071-9001'),
        );
    }
}
