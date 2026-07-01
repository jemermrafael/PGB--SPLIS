<?php

namespace App\Services;

use App\Enums\ObBlockType;
use App\Models\ObBlock;
use App\Models\ObDocument;

class ObDocumentTemplateService
{
    /**
     * Seed the default Order of Business skeleton for a new document.
     */
    public function seedDefaultBlocks(ObDocument $document): void
    {
        $blocks = [
            ['type' => ObBlockType::RomanSection, 'content' => ['numeral' => 'I.', 'title' => 'ROLL CALL', 'body' => '', 'sub_label' => '']],
            ['type' => ObBlockType::RomanSection, 'content' => ['numeral' => 'II', 'title' => 'APPEARANCE OF GUEST/S', 'body' => '', 'sub_label' => '']],
            ['type' => ObBlockType::RomanSection, 'content' => ['numeral' => 'III.', 'title' => '', 'body' => '', 'sub_label' => '']],
            ['type' => ObBlockType::RomanSection, 'content' => ['numeral' => 'IV', 'title' => 'COMMITTEE REPORT', 'body' => '', 'sub_label' => '']],
            ['type' => ObBlockType::RomanSection, 'content' => ['numeral' => 'V', 'title' => 'PRIVILEGE HOUR', 'body' => '', 'sub_label' => '']],
            [
                'type' => ObBlockType::RomanSection,
                'content' => [
                    'numeral' => 'VI',
                    'title' => 'CALENDAR OF BUSINESS',
                    'body' => '',
                    'sub_label' => '',
                ],
            ],
            ['type' => ObBlockType::SubsectionLabel, 'content' => ['text' => 'A. UNFINISHED BUSINESS']],
            ['type' => ObBlockType::SubsectionLabel, 'content' => ['text' => 'B. BUSINESS FOR THE DAY']],
            ['type' => ObBlockType::SubsectionLabel, 'content' => ['text' => '1. MEASURES FOR 2ND READING']],
            ['type' => ObBlockType::SubsectionLabel, 'content' => ['text' => '2. MEASURES FOR 3RD READING']],
            ['type' => ObBlockType::SubsectionLabel, 'content' => ['text' => 'C. UNASSIGNED MATTERS']],
            ['type' => ObBlockType::SubsectionLabel, 'content' => ['text' => '1. URGENT REQUEST/S']],
            ['type' => ObBlockType::SubsectionLabel, 'content' => ['text' => '2. REGULAR UNASSIGNED BUSINESS']],
            [
                'type' => ObBlockType::RomanSection,
                'content' => [
                    'numeral' => 'VII',
                    'title' => 'ANNOUNCEMENTS/INFORMATION/CORRESPONDENCE',
                    'body' => '',
                    'sub_label' => '',
                ],
            ],
            ['type' => ObBlockType::Adjournment, 'content' => []],
        ];

        foreach ($blocks as $index => $block) {
            ObBlock::create([
                'ob_document_id' => $document->id,
                'type' => $block['type'],
                'sort_order' => $index + 1,
                'content' => $block['content'],
            ]);
        }
    }
}
