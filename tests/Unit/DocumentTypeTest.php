<?php

namespace Tests\Unit;

use App\Support\DocumentType;
use PHPUnit\Framework\TestCase;

class DocumentTypeTest extends TestCase
{
    public function test_migrated_records_use_resolution(): void
    {
        $this->assertSame('resolution', DocumentType::forMigratedRecord());
    }

    public function test_infer_ordinance_from_title_prefix(): void
    {
        $this->assertSame(DocumentType::ORDINANCE, DocumentType::infer('12-2024', 'AN ORDINANCE APPROVING THE BUDGET'));
        $this->assertSame(DocumentType::ORDINANCE, DocumentType::infer('12-2024', 'A ORDINANCE AMENDING SECTION 2'));
        $this->assertSame(DocumentType::ORDINANCE, DocumentType::infer('12-2024', 'ORDINANCE NO. 5 SERIES OF 2024'));
    }

    public function test_infer_resolution_when_title_only_mentions_ordinance(): void
    {
        $this->assertSame(
            DocumentType::RESOLUTION,
            DocumentType::infer('12-2024', 'RESOLUTION APPROVING MUNICIPAL ORDINANCE NO. 3')
        );
    }

    public function test_infer_ordinance_from_number_pattern(): void
    {
        $this->assertSame(DocumentType::ORDINANCE, DocumentType::infer('12-O-2024', 'Some title'));
    }
}
