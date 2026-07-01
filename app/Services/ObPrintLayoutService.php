<?php

namespace App\Services;

use App\Enums\ObBlockType;
use App\Enums\ObDocumentSection;
use App\Models\ObBlock;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ObPrintLayoutService
{
    /**
     * @param  Collection<int, ObBlock>  $blocks
     * @return array<string, mixed>
     */
    public function build(Collection $blocks): array
    {
        $paragraphs = [];
        $agendaBySection = [];
        $correspondence = [];

        foreach ($blocks as $block) {
            if ($block->type === ObBlockType::Paragraph) {
                $area = $block->content['area'] ?? 'general';
                $paragraphs[$area] = (string) ($block->content['text'] ?? '');
            }

            if ($block->type === ObBlockType::AgendaLine) {
                $section = $block->content['section'] ?? ObDocumentSection::RegularUnassigned->value;
                $agendaBySection[$section] ??= [];
                $agendaBySection[$section][] = $block;
            }

            if ($block->type === ObBlockType::CorrespondenceLine) {
                $correspondence[] = $block;
            }
        }

        return [
            'guests' => $paragraphs['guests'] ?? '',
            'minutes' => $paragraphs['minutes'] ?? '',
            'privilege' => $paragraphs['privilege'] ?? '',
            'committee_reports' => $agendaBySection[ObDocumentSection::CommitteeReport->value] ?? [],
            'unfinished' => $this->groupByCommittee($agendaBySection[ObDocumentSection::Unfinished->value] ?? []),
            'second_reading' => $agendaBySection[ObDocumentSection::SecondReading->value] ?? [],
            'third_reading' => $agendaBySection[ObDocumentSection::ThirdReading->value] ?? [],
            'urgent' => $agendaBySection[ObDocumentSection::Urgent->value] ?? [],
            'regular_unassigned' => $this->groupByCommittee($agendaBySection[ObDocumentSection::RegularUnassigned->value] ?? []),
            'announcements' => array_merge(
                $agendaBySection[ObDocumentSection::Announcement->value] ?? [],
                $correspondence,
            ),
        ];
    }

    /**
     * @param  list<ObBlock>  $blocks
     * @return list<array{committee_name: string, chair_name: string, items: list<ObBlock>}>
     */
    protected function groupByCommittee(array $blocks): array
    {
        $groups = [];

        foreach ($blocks as $block) {
            $name = trim((string) ($block->content['committee_name'] ?? '')) ?: 'UNASSIGNED COMMITTEE';
            $key = Str::upper($name);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'committee_name' => $name,
                    'chair_name' => (string) ($block->content['chair_name'] ?? ''),
                    'items' => [],
                ];
            }

            if ($groups[$key]['chair_name'] === '' && filled($block->content['chair_name'] ?? null)) {
                $groups[$key]['chair_name'] = (string) $block->content['chair_name'];
            }

            $groups[$key]['items'][] = $block;
        }

        return array_values($groups);
    }
}
