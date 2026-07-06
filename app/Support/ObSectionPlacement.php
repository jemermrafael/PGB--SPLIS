<?php

namespace App\Support;

use App\Enums\ObBlockType;
use App\Models\ObBlock;
use App\Models\ObDocument;
use Illuminate\Support\Collection;

class ObSectionPlacement
{
    /**
     * Block id to insert after so new agenda rows land at the end of the chosen section.
     */
    public static function insertAfterBlockId(ObDocument $document, string $section): ?int
    {
        /** @var Collection<int, ObBlock> $blocks */
        $blocks = $document->blocks()->orderBy('sort_order')->get();

        if ($blocks->isEmpty()) {
            return null;
        }

        if ($section === 'committee_reports') {
            return self::insertAfterCommitteeReports($blocks);
        }

        $bounds = self::resolveSectionBounds($blocks, $section);

        if ($bounds === null) {
            return null;
        }

        [$startIndex, $endIndex] = $bounds;
        $sectionBlocks = $blocks->slice($startIndex, $endIndex - $startIndex);
        $startBlock = $sectionBlocks->first();
        $contentMatcher = self::contentMatcher($section);

        if ($contentMatcher === null || $startBlock === null) {
            return null;
        }

        $lastContent = $sectionBlocks
            ->filter(fn (ObBlock $block) => $contentMatcher($block))
            ->last();

        return ($lastContent ?? $startBlock)->id;
    }

    /**
     * @param  Collection<int, ObBlock>  $blocks
     */
    protected static function insertAfterCommitteeReports(Collection $blocks): ?int
    {
        $endIndex = $blocks->search(fn (ObBlock $block) => self::isPrivilegeSectionStart($block));

        if ($endIndex === false) {
            $endIndex = $blocks->count();
        }

        $anchor = null;
        $lastReport = null;

        foreach ($blocks->take($endIndex) as $block) {
            if (self::isCommitteeReportAnchor($block)) {
                $anchor = $block;
            }

            if ($block->type === ObBlockType::CommitteeReport) {
                $lastReport = $block;
            }
        }

        return ($lastReport ?? $anchor)?->id;
    }

    public static function sectionBounds(Collection $blocks, string $section): ?array
    {
        return self::resolveSectionBounds($blocks, $section);
    }

    /**
     * @param  Collection<int, ObBlock>  $blocks
     * @return array{0: int, 1: int}|null  [zone start sort_order, zone end sort_order (exclusive marker)]
     */
    public static function unfinishedZoneSortBounds(Collection $blocks): ?array
    {
        $bounds = self::resolveSectionBounds($blocks, 'unfinished');
        if ($bounds === null) {
            return null;
        }

        [$startIndex, $endIndex] = $bounds;
        $values = $blocks->values();
        $startBlock = $values[$startIndex] ?? null;
        $endBlock = $values[$endIndex] ?? null;

        if ($startBlock === null) {
            return null;
        }

        return [
            $startBlock->sort_order,
            $endBlock?->sort_order ?? PHP_INT_MAX,
        ];
    }

    /**
     * @param  Collection<int, ObBlock>  $blocks
     * @return array{0: int, 1: int}|null
     */
    protected static function resolveSectionBounds(Collection $blocks, string $section): ?array
    {
        $startIndex = null;

        foreach ($blocks->values() as $index => $block) {
            if ($startIndex === null) {
                if (self::isSectionStart($section, $block)) {
                    $startIndex = $index;
                }

                continue;
            }

            if (self::isSectionEnd($section, $block)) {
                return [$startIndex, $index];
            }
        }

        if ($startIndex === null) {
            return null;
        }

        return [$startIndex, $blocks->count()];
    }

    protected static function isSectionStart(string $section, ObBlock $block): bool
    {
        return match ($section) {
            'unfinished' => self::isUnfinishedStart($block),
            'business_2nd' => self::isSubsectionContaining($block, '2ND READING'),
            'business_3rd' => self::isSubsectionContaining($block, '3RD READING'),
            'unassigned_urgent' => self::isSubsectionContaining($block, 'URGENT REQUEST'),
            'unassigned_regular' => self::isSubsectionContaining($block, 'REGULAR UNASSIGNED'),
            default => false,
        };
    }

    protected static function isSectionEnd(string $section, ObBlock $block): bool
    {
        return match ($section) {
            'unfinished' => self::isBusinessForTheDayMarker($block)
                || self::isRomanNumeral($block, 'VII')
                || self::isHeadingContaining($block, 'ANNOUNCEMENTS')
                || $block->type === ObBlockType::Adjournment,
            'business_2nd' => self::isSubsectionContaining($block, '3RD READING'),
            'business_3rd' => self::isSubsectionContaining($block, 'UNASSIGNED MATTERS'),
            'unassigned_urgent' => self::isSubsectionContaining($block, 'REGULAR UNASSIGNED'),
            'unassigned_regular' => self::isRomanNumeral($block, 'VII')
                || self::isHeadingContaining($block, 'ANNOUNCEMENTS')
                || $block->type === ObBlockType::Adjournment,
            default => false,
        };
    }

    /**
     * @return (callable(ObBlock): bool)|null
     */
    protected static function contentMatcher(string $section): ?callable
    {
        return match ($section) {
            'unfinished' => fn (ObBlock $block) => in_array($block->type, [
                ObBlockType::UnfinishedCommittee,
                ObBlockType::UnfinishedAgenda,
            ], true),
            'business_2nd' => fn (ObBlock $block) => $block->type === ObBlockType::ReadingAgenda
                && ($block->content['reading'] ?? '2nd') !== '3rd',
            'business_3rd' => fn (ObBlock $block) => $block->type === ObBlockType::ReadingAgenda
                && ($block->content['reading'] ?? '2nd') === '3rd',
            'unassigned_urgent' => fn (ObBlock $block) => $block->type === ObBlockType::UnassignedAgenda
                && ($block->content['kind'] ?? 'regular') === 'urgent',
            'unassigned_regular' => fn (ObBlock $block) => $block->type === ObBlockType::UnassignedAgenda
                && ($block->content['kind'] ?? 'regular') !== 'urgent',
            default => null,
        };
    }

    protected static function isUnfinishedStart(ObBlock $block): bool
    {
        if (self::isSubsectionContaining($block, 'UNFINISHED BUSINESS')) {
            return true;
        }

        if ($block->type === ObBlockType::RomanSection
            && str_contains(mb_strtoupper((string) ($block->content['sub_label'] ?? '')), 'UNFINISHED BUSINESS')) {
            return true;
        }

        return false;
    }

    protected static function isBusinessForTheDayMarker(ObBlock $block): bool
    {
        if ($block->type !== ObBlockType::SubsectionLabel) {
            return false;
        }

        $text = mb_strtoupper(trim((string) ($block->content['text'] ?? '')));

        return str_contains($text, 'BUSINESS FOR THE DAY')
            && ! str_contains($text, 'UNFINISHED');
    }

    protected static function isPrivilegeSectionStart(ObBlock $block): bool
    {
        return self::isRomanNumeral($block, 'V')
            || self::isHeadingContaining($block, 'PRIVILEGE HOUR');
    }

    protected static function isRomanNumeral(ObBlock $block, string $numeral, ?string $titleContains = null): bool
    {
        if ($block->type !== ObBlockType::RomanSection) {
            return false;
        }

        if (self::normalizedNumeral((string) ($block->content['numeral'] ?? '')) !== self::normalizedNumeral($numeral)) {
            return false;
        }

        if ($titleContains !== null
            && ! str_contains(mb_strtoupper((string) ($block->content['title'] ?? '')), mb_strtoupper($titleContains))) {
            return false;
        }

        return true;
    }

    protected static function normalizedNumeral(string $numeral): string
    {
        return rtrim(trim($numeral), '.');
    }

    protected static function isHeadingContaining(ObBlock $block, string $needle): bool
    {
        if ($block->type !== ObBlockType::Heading) {
            return false;
        }

        return str_contains(mb_strtoupper((string) ($block->content['text'] ?? '')), mb_strtoupper($needle));
    }

    protected static function isSubsectionContaining(ObBlock $block, string $needle): bool
    {
        if ($block->type !== ObBlockType::SubsectionLabel) {
            return false;
        }

        return str_contains(mb_strtoupper((string) ($block->content['text'] ?? '')), mb_strtoupper($needle));
    }

    protected static function isCommitteeReportAnchor(ObBlock $block): bool
    {
        if (self::isRomanNumeral($block, 'IV') && str_contains(mb_strtoupper((string) ($block->content['title'] ?? '')), 'COMMITTEE')) {
            return true;
        }

        return self::isHeadingContaining($block, 'COMMITTEE REPORT');
    }
}
