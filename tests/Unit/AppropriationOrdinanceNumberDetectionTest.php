<?php

namespace Tests\Unit;

use App\Models\AgendaItem;
use App\Support\AgendaMeasureType;
use App\Support\OrdinanceNumberParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppropriationOrdinanceNumberDetectionTest extends TestCase
{
    #[DataProvider('appropriationReferences')]
    public function test_detects_appropriation_ordinance_number_text(string $value): void
    {
        $this->assertTrue(OrdinanceNumberParser::looksLikeAppropriationOrdinanceReference($value));
        $this->assertFalse(OrdinanceNumberParser::looksLikeOrdinanceReference($value));
        $this->assertSame(
            AgendaMeasureType::APPROPRIATION_ORDINANCE,
            AgendaItem::inferMeasureType(null, $value),
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function appropriationReferences(): array
    {
        return [
            'ao' => ['AO No. 1'],
            'a_o' => ['A.O. No. 2'],
            'appro_ord' => ['Appro. Ord. No. 51'],
            'app_ord' => ['App. Ord. 3'],
            'appropriation_ord' => ['Appropriation Ord. No. 4'],
        ];
    }

    public function test_plain_ordinance_reference_still_detected(): void
    {
        $this->assertFalse(OrdinanceNumberParser::looksLikeAppropriationOrdinanceReference('Ord. No. 22'));
        $this->assertTrue(OrdinanceNumberParser::looksLikeOrdinanceReference('Ord. No. 22'));
        $this->assertSame(
            AgendaMeasureType::ORDINANCE,
            AgendaItem::inferMeasureType(null, 'Ord. No. 22'),
        );
    }
}
