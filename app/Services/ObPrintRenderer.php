<?php

namespace App\Services;

use App\Enums\ObBlockType;
use App\Models\ObBlock;
use App\Support\CommitteeLookup;
use App\Support\ObAgendaSnapshot;
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
            if ($buffer === null) {
                return;
            }

            if (($buffer['type'] ?? '') === 'business_day_table') {
                $segments[] = $this->finalizeBusinessDaySegment($buffer);
            } else {
                $segments[] = $buffer;
            }

            $buffer = null;
        };

        foreach ($blocks as $block) {
            $type = $block->type;

            if ($type === ObBlockType::CommitteeReport) {
                if (($buffer['type'] ?? null) !== 'committee_reports_table') {
                    $flush();
                    $buffer = ['type' => 'committee_reports_table', 'rows' => []];
                }
                $row = ObAgendaSnapshot::enrichCommitteeReportRow($block->content ?? []);
                $rows = &$buffer['rows'];
                if ($rows !== []
                    && ObAgendaSnapshot::committeeReportKey((array) end($rows)) === ObAgendaSnapshot::committeeReportKey($row)) {
                    $rows[array_key_last($rows)] = ObAgendaSnapshot::mergeCommitteeReportRows((array) end($rows), $row);
                } else {
                    $rows[] = $row;
                }

                continue;
            }

            if ($type === ObBlockType::UnfinishedCommittee) {
                $flush();
                $content = $block->content ?? [];
                $committeeId = is_numeric($content['committee_id'] ?? null) ? (int) $content['committee_id'] : null;
                $committeeName = (string) ($content['committee_name'] ?? '');
                $buffer = [
                    'type' => 'unfinished_group',
                    'committee_name' => ObCommitteeFormatter::resolvedLabel($committeeId, $committeeName),
                    'chair_name' => CommitteeLookup::chairFor($committeeId, $committeeName) ?: ($content['chair_name'] ?? ''),
                    'items' => [],
                ];

                continue;
            }

            if ($type === ObBlockType::UnfinishedAgenda) {
                $content = $block->content ?? [];
                $committeeId = is_numeric($content['committee_id'] ?? null) ? (int) $content['committee_id'] : null;
                $committee = ObCommitteeFormatter::resolvedLabel($committeeId, (string) ($content['committee_name'] ?? ''));
                if (($buffer['type'] ?? null) !== 'unfinished_group'
                    || ($buffer['committee_name'] ?? '') !== $committee) {
                    $flush();
                    $buffer = [
                        'type' => 'unfinished_group',
                        'committee_name' => $committee,
                        'chair_name' => CommitteeLookup::chairFor($committeeId, (string) ($content['committee_name'] ?? '')),
                        'items' => [],
                    ];
                }
                $buffer['items'][] = $content;

                continue;
            }

            if ($type === ObBlockType::ReadingAgenda) {
                if (($buffer['type'] ?? null) !== 'business_day_table') {
                    $flush();
                    $this->ensureBusinessDayBuffer($buffer);
                }
                $buffer['rows'][] = ['kind' => 'agenda', 'row' => $block->content ?? []];

                continue;
            }

            if ($type === ObBlockType::UnassignedAgenda) {
                $kind = ($block->content['kind'] ?? 'regular') === 'urgent' ? 'urgent' : 'regular';
                $row = ObAgendaSnapshot::enrichUnassignedRow($block->content ?? [], $block->agendaItem);

                if ($kind === 'urgent') {
                    if (($buffer['type'] ?? null) !== 'business_day_table') {
                        $flush();
                        $this->ensureBusinessDayBuffer($buffer);
                    }
                    $buffer['rows'][] = ['kind' => 'agenda', 'row' => $row];
                } else {
                    if (($buffer['type'] ?? null) !== 'unassigned_regular_table') {
                        $flush();
                        $buffer = [
                            'type' => 'unassigned_regular_table',
                            'subsection' => '2. REGULAR UNASSIGNED BUSINESS',
                            'rows' => [],
                        ];
                    }
                    $buffer['rows'][] = $row;
                }

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

            if ($type === ObBlockType::RomanSection) {
                $numeral = trim((string) ($block->content['numeral'] ?? ''));
                $title = trim((string) ($block->content['title'] ?? ''));
                $normalized = ObRomanNumeral::normalize($numeral);
                $isViCalendar = $normalized === 'VI' && str_contains(mb_strtoupper($title), 'CALENDAR');

                if (! ($isViCalendar && ($buffer['type'] ?? null) === 'privilege_calendar_table')) {
                    $flush();
                }

                if ($normalized === 'IV' || str_starts_with(mb_strtoupper($title), 'COMMITTEE REPORT')) {
                    $buffer = ['type' => 'committee_reports_table', 'rows' => []];

                    continue;
                }

                if ($normalized === 'V' && str_contains(mb_strtoupper($title), 'PRIVILEGE')) {
                    $buffer = [
                        'type' => 'privilege_calendar_table',
                        'rows' => [[
                            'numeral' => ObRomanNumeral::display($numeral),
                            'title' => $title,
                        ]],
                    ];

                    continue;
                }

                if ($normalized === 'VI' && str_contains(mb_strtoupper($title), 'CALENDAR')) {
                    if (($buffer['type'] ?? null) === 'privilege_calendar_table') {
                        $buffer['rows'][] = [
                            'numeral' => ObRomanNumeral::display($numeral),
                            'title' => $title,
                        ];
                    } else {
                        $buffer = [
                            'type' => 'privilege_calendar_table',
                            'rows' => [[
                                'numeral' => ObRomanNumeral::display($numeral),
                                'title' => $title,
                            ]],
                        ];
                    }

                    continue;
                }

                if ($normalized === 'VII' || str_contains(mb_strtoupper($title), 'ANNOUNCEMENTS')) {
                    $buffer = [
                        'type' => 'announcements_closing',
                        'rows' => [],
                        'include_adjournment' => false,
                    ];
                    $announcementsOpen = true;

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

            $flush();

            if ($type === ObBlockType::Heading) {
                $mapped = $this->mapLegacyHeading($block->content['text'] ?? '');
                if ($mapped !== null) {
                    if (($mapped['type'] ?? '') === 'committee_reports_table') {
                        $buffer = ['type' => 'committee_reports_table', 'rows' => []];
                    } else {
                        $segments[] = $mapped;
                    }
                }

                continue;
            }

            if ($type === ObBlockType::SubsectionLabel) {
                $text = (string) ($block->content['text'] ?? '');

                if (str_contains(mb_strtoupper($text), 'ANNOUNCEMENTS')) {
                    $flush();
                    $buffer = [
                        'type' => 'announcements_closing',
                        'rows' => [],
                        'include_adjournment' => false,
                    ];
                    $announcementsOpen = true;
                } elseif (str_contains(mb_strtoupper($text), 'UNFINISHED BUSINESS')) {
                    $flush();
                    $segments[] = ['type' => 'subsection', 'text' => $text];
                } elseif ($this->isBusinessDaySubsection($text)) {
                    if (($buffer['type'] ?? null) !== 'business_day_table') {
                        $flush();
                        $this->ensureBusinessDayBuffer($buffer);
                    }
                    $buffer['rows'][] = ['kind' => 'subsection', 'text' => $text];
                } elseif (str_contains(mb_strtoupper($text), 'REGULAR UNASSIGNED')) {
                    $flush();
                    $buffer = [
                        'type' => 'unassigned_regular_table',
                        'subsection' => $text,
                        'rows' => [],
                    ];
                } else {
                    $flush();
                    $segments[] = ['type' => 'subsection', 'text' => $text];
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

        return $this->mergeAdjacentAnnouncementsSegments(
            $this->mergeAdjacentCommitteeReportSegments($segments),
        );
    }

    /**
     * @param  array<string, mixed>|null  $buffer
     */
    protected function ensureBusinessDayBuffer(?array &$buffer): void
    {
        if (($buffer['type'] ?? null) !== 'business_day_table') {
            $buffer = [
                'type' => 'business_day_table',
                'rows' => [],
            ];
        }
    }

    protected function isBusinessDaySubsection(string $text): bool
    {
        $text = mb_strtoupper(trim($text));

        return str_contains($text, 'BUSINESS FOR THE DAY')
            || str_contains($text, 'MEASURES FOR 2ND READING')
            || str_contains($text, 'MEASURES FOR 3RD READING')
            || str_contains($text, 'UNASSIGNED MATTERS')
            || str_contains($text, 'URGENT REQUEST');
    }

    /**
     * @param  array<string, mixed>  $segment
     * @return array<string, mixed>
     */
    protected function finalizeBusinessDaySegment(array $segment): array
    {
        $rows = [];
        $count = count($segment['rows'] ?? []);

        for ($i = 0; $i < $count; $i++) {
            $row = $segment['rows'][$i];
            $rows[] = $row;

            if (($row['kind'] ?? '') !== 'subsection') {
                continue;
            }

            $text = mb_strtoupper((string) ($row['text'] ?? ''));
            $needsNone = str_contains($text, '2ND READING')
                || str_contains($text, '3RD READING');

            if (! $needsNone) {
                continue;
            }

            $next = $segment['rows'][$i + 1] ?? null;
            if (($next['kind'] ?? '') === 'agenda') {
                continue;
            }

            $rows[] = ['kind' => 'none'];
        }

        $segment['rows'] = $rows;

        return $segment;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    protected function mergeAdjacentCommitteeReportSegments(array $segments): array
    {
        $merged = [];

        foreach ($segments as $segment) {
            $previous = $merged[array_key_last($merged)] ?? null;

            if (($segment['type'] ?? '') === 'committee_reports_table'
                && is_array($previous)
                && ($previous['type'] ?? '') === 'committee_reports_table') {
                $previous['rows'] = array_merge($previous['rows'] ?? [], $segment['rows'] ?? []);
                $merged[array_key_last($merged)] = $previous;

                continue;
            }

            $merged[] = $segment;
        }

        return $merged;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    protected function mergeAdjacentAnnouncementsSegments(array $segments): array
    {
        $merged = [];

        foreach ($segments as $segment) {
            $previous = $merged[array_key_last($merged)] ?? null;

            if (($segment['type'] ?? '') === 'announcements_closing'
                && is_array($previous)
                && ($previous['type'] ?? '') === 'announcements_closing') {
                $previous['rows'] = array_merge($previous['rows'] ?? [], $segment['rows'] ?? []);
                $previous['include_adjournment'] = ($segment['include_adjournment'] ?? false)
                    || ($previous['include_adjournment'] ?? false);
                $merged[array_key_last($merged)] = $previous;

                continue;
            }

            $merged[] = $segment;
        }

        return $merged;
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
                'type' => 'privilege_calendar_table',
                'rows' => [[
                    'numeral' => ObRomanNumeral::display('V'),
                    'title' => 'PRIVILEGE HOUR',
                ]],
            ],
            str_contains($normalized, 'CALENDAR OF BUSINESS') => [
                'type' => 'privilege_calendar_table',
                'rows' => [
                    [
                        'numeral' => ObRomanNumeral::display('V'),
                        'title' => 'PRIVILEGE HOUR',
                    ],
                    [
                        'numeral' => ObRomanNumeral::display('VI'),
                        'title' => 'CALENDAR OF BUSINESS',
                    ],
                ],
            ],
            default => null,
        };
    }
}
