<?php

namespace App\Services;

use App\Enums\ObBlockType;
use App\Models\ObBlock;
use App\Support\ObCommitteeFormatter;
use App\Support\ObRomanNumeral;
use Illuminate\Support\Collection;

class ObPrintRenderer
{
    /**
     * @param  Collection<int, ObBlock>  $blocks
     * @return list<array<string, mixed>>
     */
    public function segments(Collection $blocks): array
    {
        $segments = [];
        $buffer = null;
        $announcementsOpen = false;

        $flush = function () use (&$segments, &$buffer): void {
            if ($buffer !== null) {
                $segments[] = $buffer;
                $buffer = null;
            }
        };

        foreach ($blocks as $block) {
            $type = $block->type;

            if ($type === ObBlockType::CommitteeReport) {
                if (($buffer['type'] ?? null) !== 'committee_reports_table') {
                    $flush();
                    $buffer = ['type' => 'committee_reports_table', 'rows' => []];
                }
                $buffer['rows'][] = $block->content ?? [];

                continue;
            }

            if ($type === ObBlockType::UnfinishedCommittee) {
                $flush();
                $buffer = [
                    'type' => 'unfinished_group',
                    'committee_name' => $block->content['committee_name'] ?? '',
                    'chair_name' => $block->content['chair_name'] ?? '',
                    'items' => [],
                ];

                continue;
            }

            if ($type === ObBlockType::UnfinishedAgenda) {
                $committee = ObCommitteeFormatter::spCommitteeLabel($block->content['committee_name'] ?? '');
                if (($buffer['type'] ?? null) !== 'unfinished_group'
                    || ($buffer['committee_name'] ?? '') !== $committee) {
                    $flush();
                    $buffer = [
                        'type' => 'unfinished_group',
                        'committee_name' => $committee,
                        'chair_name' => '',
                        'items' => [],
                    ];
                }
                $buffer['items'][] = $block->content ?? [];

                continue;
            }

            if ($type === ObBlockType::ReadingAgenda) {
                $reading = ($block->content['reading'] ?? '2nd') === '3rd' ? '3rd' : '2nd';
                $key = $reading === '3rd' ? 'reading_3rd_table' : 'reading_2nd_table';
                if (($buffer['type'] ?? null) !== $key) {
                    $flush();
                    $buffer = ['type' => $key, 'rows' => []];
                }
                $buffer['rows'][] = $block->content ?? [];

                continue;
            }

            if ($type === ObBlockType::UnassignedAgenda) {
                $kind = ($block->content['kind'] ?? 'regular') === 'urgent' ? 'urgent' : 'regular';
                $key = $kind === 'urgent' ? 'unassigned_urgent_table' : 'unassigned_regular_table';
                if (($buffer['type'] ?? null) !== $key) {
                    $flush();
                    $buffer = ['type' => $key, 'rows' => []];
                }
                $buffer['rows'][] = $block->content ?? [];

                continue;
            }

            if ($type === ObBlockType::Announcement) {
                if (($buffer['type'] ?? null) !== 'announcements_closing') {
                    $flush();
                    $buffer = [
                        'type' => 'announcements_closing',
                        'rows' => [],
                        'include_adjournment' => false,
                    ];
                }
                $buffer['rows'][] = $block->content ?? [];
                $announcementsOpen = true;

                continue;
            }

            if ($type === ObBlockType::Adjournment) {
                if (($buffer['type'] ?? null) !== 'announcements_closing') {
                    $flush();
                    $buffer = [
                        'type' => 'announcements_closing',
                        'rows' => [],
                        'include_adjournment' => true,
                    ];
                } else {
                    $buffer['include_adjournment'] = true;
                }
                $announcementsOpen = true;

                continue;
            }

            $flush();

            if ($type === ObBlockType::RomanSection) {
                $numeral = trim((string) ($block->content['numeral'] ?? ''));
                $title = trim((string) ($block->content['title'] ?? ''));
                $subLabel = trim((string) ($block->content['sub_label'] ?? ''));

                if (ObRomanNumeral::normalize($numeral) === 'IV' || str_starts_with(mb_strtoupper($title), 'COMMITTEE REPORT')) {
                    $segments[] = ['type' => 'committee_reports_table', 'rows' => []];

                    continue;
                }

                if (ObRomanNumeral::normalize($numeral) === 'VII' || str_contains(mb_strtoupper($title), 'ANNOUNCEMENTS')) {
                    $flush();
                    $buffer = [
                        'type' => 'announcements_closing',
                        'rows' => [],
                        'include_adjournment' => false,
                    ];
                    $announcementsOpen = true;

                    continue;
                }

                if ($subLabel !== '') {
                    $segments[] = [
                        'type' => 'calendar_section',
                        'numeral' => ObRomanNumeral::display($numeral),
                        'title' => $title,
                        'sub_label' => $subLabel,
                    ];

                    continue;
                }

                $segments[] = [
                    'type' => 'roman_section',
                    'numeral' => ObRomanNumeral::display($numeral),
                    'title' => $title,
                    'body' => $block->content['body'] ?? '',
                ];

                continue;
            }

            if ($type === ObBlockType::Heading) {
                $mapped = $this->mapLegacyHeading($block->content['text'] ?? '');
                if ($mapped !== null) {
                    $segments[] = $mapped;
                }

                continue;
            }

            if ($type === ObBlockType::SubsectionLabel) {
                $text = $block->content['text'] ?? '';
                if (str_contains(mb_strtoupper($text), 'ANNOUNCEMENTS')) {
                    $flush();
                    $buffer = [
                        'type' => 'announcements_closing',
                        'rows' => [],
                        'include_adjournment' => false,
                    ];
                    $announcementsOpen = true;
                } else {
                    $segments[] = [
                        'type' => 'subsection',
                        'text' => $text,
                    ];
                }

                continue;
            }

            if ($type === ObBlockType::Paragraph) {
                if (filled($block->content['text'] ?? null)) {
                    $segments[] = [
                        'type' => 'paragraph',
                        'text' => $block->content['text'] ?? '',
                    ];
                }

                continue;
            }

            if ($type === ObBlockType::PageBreak) {
                $segments[] = ['type' => 'page_break'];

                continue;
            }

            if ($type === ObBlockType::AgendaLine) {
                $segments[] = [
                    'type' => 'legacy_agenda',
                    'content' => $block->content ?? [],
                ];

                continue;
            }

            $segments[] = [
                'type' => 'paragraph',
                'text' => $block->previewText(),
            ];
        }

        $flush();

        if (! $announcementsOpen) {
            $segments[] = [
                'type' => 'announcements_closing',
                'rows' => [],
                'include_adjournment' => true,
            ];
        } elseif ($buffer !== null && ($buffer['type'] ?? '') === 'announcements_closing') {
            $buffer['include_adjournment'] = true;
        }

        $flush();

        return $this->injectEmptyAgendaRows($segments);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function mapLegacyHeading(string $text): ?array
    {
        $normalized = mb_strtoupper(trim($text));

        return match (true) {
            str_contains($normalized, 'ROLL CALL') => [
                'type' => 'roman_section',
                'numeral' => 'I.',
                'title' => 'ROLL CALL',
                'body' => '',
            ],
            str_contains($normalized, 'APPEARANCE OF GUEST') => [
                'type' => 'roman_section',
                'numeral' => 'II',
                'title' => 'APPEARANCE OF GUEST/S',
                'body' => '',
            ],
            str_contains($normalized, 'JOURNAL') || str_contains($normalized, 'MINUTES') => [
                'type' => 'roman_section',
                'numeral' => 'III.',
                'title' => '',
                'body' => '',
            ],
            str_contains($normalized, 'COMMITTEE REPORT') => [
                'type' => 'committee_reports_table',
                'rows' => [],
            ],
            str_contains($normalized, 'PRIVILEGE HOUR') => [
                'type' => 'roman_section',
                'numeral' => 'V',
                'title' => 'PRIVILEGE HOUR',
                'body' => '',
            ],
            str_contains($normalized, 'CALENDAR OF BUSINESS') => [
                'type' => 'calendar_section',
                'numeral' => 'VI',
                'title' => 'CALENDAR OF BUSINESS',
                'sub_label' => 'A. UNFINISHED BUSINESS',
            ],
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    protected function injectEmptyAgendaRows(array $segments): array
    {
        $result = [];
        $count = count($segments);

        for ($i = 0; $i < $count; $i++) {
            $segment = $segments[$i];
            $result[] = $segment;

            if (($segment['type'] ?? '') !== 'subsection') {
                continue;
            }

            $text = mb_strtoupper($segment['text'] ?? '');

            if (str_contains($text, '2ND READING')) {
                $next = $segments[$i + 1] ?? null;
                if (($next['type'] ?? '') !== 'reading_2nd_table') {
                    $result[] = ['type' => 'reading_2nd_table', 'rows' => [], 'none' => true];
                }
            }

            if (str_contains($text, '3RD READING')) {
                $next = $segments[$i + 1] ?? null;
                if (($next['type'] ?? '') !== 'reading_3rd_table') {
                    $result[] = ['type' => 'reading_3rd_table', 'rows' => [], 'none' => true];
                }
            }

            if (str_contains($text, 'URGENT REQUEST')) {
                $next = $segments[$i + 1] ?? null;
                if (($next['type'] ?? '') !== 'unassigned_urgent_table') {
                    $result[] = ['type' => 'unassigned_urgent_table', 'rows' => [], 'none' => true];
                }
            }
        }

        return $result;
    }
}
