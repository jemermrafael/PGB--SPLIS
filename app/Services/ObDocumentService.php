<?php

namespace App\Services;

use App\Enums\ObBlockType;
use App\Models\AgendaItem;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Support\CommitteeLookup;
use App\Support\ObAgendaSnapshot;
use App\Support\ObBlockDefaults;
use App\Support\ObCommitteeFormatter;
use App\Support\ObRomanNumeral;
use App\Support\ObSectionPlacement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ObDocumentService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function blocksPayload(ObDocument $document): array
    {
        return $document->blocks()
            ->with('agendaItem:id,tracking_no,title')
            ->get()
            ->map(fn (ObBlock $block) => $this->blockPayload($block))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function blockPayload(ObBlock $block): array
    {
        $content = $block->content ?? [];

        if ($block->type === ObBlockType::RomanSection) {
            $content = ObRomanNumeral::formatSectionContent($content);
        }

        return [
            'id' => $block->id,
            'type' => $block->type->value,
            'type_label' => $block->type->label(),
            'sort_order' => $block->sort_order,
            'content' => $content,
            'agenda_item_id' => $block->agenda_item_id,
            'preview' => $block->previewText(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function documentPayload(ObDocument $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'status' => $document->status,
            'status_label' => $document->statusLabel(),
            'next_session_agenda_no' => $document->next_session_agenda_no,
        ];
    }

    public function addBlock(
        ObDocument $document,
        ObBlockType $type,
        ?array $content = null,
        ?int $afterBlockId = null,
        ?int $agendaItemId = null,
    ): ObBlock {
        $sortOrder = $this->nextSortOrder($document, $afterBlockId);

        $this->shiftBlocksDown($document, $sortOrder);

        return ObBlock::create([
            'ob_document_id' => $document->id,
            'type' => $type,
            'sort_order' => $sortOrder,
            'content' => $content ?? ObBlockDefaults::empty($type),
            'agenda_item_id' => $agendaItemId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public function updateBlock(ObBlock $block, array $content): ObBlock
    {
        $regroupUnfinished = false;

        if ($block->type === ObBlockType::UnfinishedAgenda) {
            $regroupUnfinished = $this->unfinishedCommitteeKey($block->content ?? [])
                !== $this->unfinishedCommitteeKey($content);
        }

        if ($block->type === ObBlockType::CommitteeReport) {
            $content = $this->enrichCommitteeReportContent($content);
        }

        if ($block->type === ObBlockType::RomanSection) {
            $content = ObRomanNumeral::formatSectionContent($content);
        }

        $block->update(['content' => $content]);

        if ($regroupUnfinished) {
            $this->regroupUnfinishedBusiness($block->obDocument);
        }

        return $block->fresh(['agendaItem']);
    }

    public function regroupUnfinishedBusiness(ObDocument $document): void
    {
        $blocks = $document->blocks()->orderBy('sort_order')->get();
        $zone = ObSectionPlacement::unfinishedZoneSortBounds($blocks);

        if ($zone === null) {
            return;
        }

        [$zoneStartSort, $zoneEndSort] = $zone;

        $prefixIds = $blocks
            ->where('sort_order', '<=', $zoneStartSort)
            ->pluck('id')
            ->all();

        $suffixIds = $blocks
            ->where('sort_order', '>=', $zoneEndSort)
            ->pluck('id')
            ->all();

        /** @var Collection<int, ObBlock> $agendas */
        $agendas = $blocks
            ->filter(fn (ObBlock $block) => $block->sort_order > $zoneStartSort
                && $block->sort_order < $zoneEndSort
                && $block->type === ObBlockType::UnfinishedAgenda)
            ->values();

        ObBlock::query()
            ->where('ob_document_id', $document->id)
            ->where('type', ObBlockType::UnfinishedCommittee)
            ->where('sort_order', '>', $zoneStartSort)
            ->where('sort_order', '<', $zoneEndSort)
            ->delete();

        if ($agendas->isEmpty()) {
            return;
        }

        $groups = [];
        $groupOrder = [];

        foreach ($agendas as $agendaBlock) {
            $key = $this->unfinishedCommitteeKey($agendaBlock->content ?? []);

            if (! array_key_exists($key, $groups)) {
                $groups[$key] = [];
                $groupOrder[$key] = $agendaBlock->sort_order;
            }

            $groups[$key][] = $agendaBlock;
        }

        uasort($groupOrder, fn (int $a, int $b) => $a <=> $b);

        $middleIds = [];

        foreach (array_keys($groupOrder) as $key) {
            $items = $groups[$key];

            if ($key !== '') {
                $header = ObBlock::create([
                    'ob_document_id' => $document->id,
                    'type' => ObBlockType::UnfinishedCommittee,
                    'sort_order' => 0,
                    'content' => $this->unfinishedCommitteeHeaderContent($items[0]),
                ]);
                $middleIds[] = $header->id;
            }

            foreach ($items as $item) {
                $middleIds[] = $item->id;
            }
        }

        $this->reorderBlocks($document, array_merge($prefixIds, $middleIds, $suffixIds));
    }

    public function deleteBlock(ObBlock $block): void
    {
        $document = $block->obDocument;
        $deletedOrder = $block->sort_order;

        $block->delete();

        ObBlock::query()
            ->where('ob_document_id', $document->id)
            ->where('sort_order', '>', $deletedOrder)
            ->decrement('sort_order');
    }

    /**
     * @param  list<int>  $blockIds
     */
    public function reorderBlocks(ObDocument $document, array $blockIds): void
    {
        $existing = ObBlock::query()
            ->where('ob_document_id', $document->id)
            ->pluck('id')
            ->all();

        if (count($blockIds) !== count($existing)
            || array_diff($blockIds, $existing) !== []
            || array_diff($existing, $blockIds) !== []) {
            throw ValidationException::withMessages([
                'order' => ['Invalid block order.'],
            ]);
        }

        DB::transaction(function () use ($blockIds): void {
            foreach ($blockIds as $index => $blockId) {
                ObBlock::whereKey($blockId)->update(['sort_order' => $index + 1]);
            }
        });
    }

    /**
     * @param  list<int>  $agendaItemIds
     * @return list<ObBlock>
     */
    public function addAgendaItems(
        ObDocument $document,
        array $agendaItemIds,
        ?int $afterBlockId = null,
        string $section = 'unassigned_regular',
        ?int $committeeId = null,
    ): array {
        $items = AgendaItem::query()
            ->whereIn('id', $agendaItemIds)
            ->orderBy('date_received')
            ->orderBy('id')
            ->get();

        $alreadyLinked = ObBlock::query()
            ->where('ob_document_id', $document->id)
            ->whereNotNull('agenda_item_id')
            ->pluck('agenda_item_id');

        $items = $items->reject(fn (AgendaItem $item) => $alreadyLinked->contains($item->id))->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'agenda_item_ids' => ['Selected agenda item(s) are already in this document.'],
            ]);
        }

        $created = [];
        $afterId = ObSectionPlacement::insertAfterBlockId($document, $section);

        if ($afterId === null) {
            throw ValidationException::withMessages([
                'section' => ['Could not find this section in the document. Make sure the default OB template sections are present.'],
            ]);
        }
        $lastCommittee = $section === 'unfinished'
            ? $this->lastUnfinishedCommitteeAt($afterId)
            : null;
        $rowNo = (int) ObBlock::query()
            ->where('ob_document_id', $document->id)
            ->where('type', ObBlockType::CommitteeReport)
            ->get()
            ->max(fn (ObBlock $block) => (int) ($block->content['row_no'] ?? 0));

        foreach ($items->values() as $index => $item) {
            if ($section === 'unfinished') {
                $committee = ObCommitteeFormatter::spCommitteeLabel($item->committee_referred);
                if ($committee !== '' && $committee !== $lastCommittee) {
                    $header = $this->addBlock(
                        $document,
                        ObBlockType::UnfinishedCommittee,
                        [
                            'committee_name' => $committee,
                            'chair_name' => CommitteeLookup::chairFor(null, $item->committee_referred),
                        ],
                        $afterId,
                    );
                    $created[] = $header;
                    $afterId = $header->id;
                    $lastCommittee = $committee;
                }
            }

            [$type, $content] = $this->agendaBlockForSection(
                $section,
                $item,
                $section === 'committee_reports' ? ++$rowNo : 0,
                blank($item->committee_referred) ? $committeeId : null,
            );

            $block = $this->addBlock(
                $document,
                $type,
                $content,
                $afterId,
                $item->id,
            );
            $created[] = $block;
            $afterId = $block->id;
        }

        return $created;
    }

    /**
     * @return array{0: ObBlockType, 1: array<string, mixed>}
     */
    protected function agendaBlockForSection(string $section, AgendaItem $item, int $rowNo = 0, ?int $committeeId = null): array
    {
        return match ($section) {
            'committee_reports' => [
                ObBlockType::CommitteeReport,
                ObAgendaSnapshot::committeeReport($item, $rowNo, $committeeId),
            ],
            'unfinished' => [
                ObBlockType::UnfinishedAgenda,
                ObAgendaSnapshot::unfinishedAgenda($item),
            ],
            'business_2nd' => [
                ObBlockType::ReadingAgenda,
                ObAgendaSnapshot::readingAgenda($item, '2nd'),
            ],
            'business_3rd' => [
                ObBlockType::ReadingAgenda,
                ObAgendaSnapshot::readingAgenda($item, '3rd'),
            ],
            'unassigned_urgent' => [
                ObBlockType::UnassignedAgenda,
                ObAgendaSnapshot::unassignedAgenda($item, 'urgent'),
            ],
            default => [
                ObBlockType::UnassignedAgenda,
                ObAgendaSnapshot::unassignedAgenda($item, 'regular'),
            ],
        };
    }

    /**
     * @return list<int>
     */
    public function allocateSessionAgendaNumbers(ObDocument $document, int $count): array
    {
        $numbers = [];
        $next = $document->next_session_agenda_no;

        for ($i = 0; $i < $count; $i++) {
            $numbers[] = $next + $i;
        }

        $document->update(['next_session_agenda_no' => $next + $count]);

        return $numbers;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDocument(ObDocument $document, array $data): ObDocument
    {
        $document->update(collect($data)->only(['title', 'status'])->filter(fn ($v) => $v !== null)->all());

        return $document->fresh();
    }

    protected function nextSortOrder(ObDocument $document, ?int $afterBlockId): int
    {
        if ($afterBlockId === null) {
            return (int) ObBlock::query()
                ->where('ob_document_id', $document->id)
                ->max('sort_order') + 1;
        }

        $after = ObBlock::query()
            ->where('ob_document_id', $document->id)
            ->whereKey($afterBlockId)
            ->firstOrFail();

        return $after->sort_order + 1;
    }

    protected function shiftBlocksDown(ObDocument $document, int $fromSortOrder): void
    {
        ObBlock::query()
            ->where('ob_document_id', $document->id)
            ->where('sort_order', '>=', $fromSortOrder)
            ->increment('sort_order');
    }

    protected function lastUnfinishedCommitteeAt(?int $afterBlockId): ?string
    {
        if ($afterBlockId === null) {
            return null;
        }

        $block = ObBlock::query()->find($afterBlockId);
        if (! $block) {
            return null;
        }

        if ($block->type === ObBlockType::UnfinishedCommittee) {
            return (string) ($block->content['committee_name'] ?? '');
        }

        if ($block->type === ObBlockType::UnfinishedAgenda) {
            return ObCommitteeFormatter::spCommitteeLabel($block->content['committee_name'] ?? '');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function unfinishedCommitteeKey(array $content): string
    {
        return ObCommitteeFormatter::spCommitteeLabel((string) ($content['committee_name'] ?? ''));
    }

    /**
     * @return array{committee_name: string, chair_name: string}
     */
    protected function unfinishedCommitteeHeaderContent(ObBlock $agendaBlock): array
    {
        $content = $agendaBlock->content ?? [];
        $rawName = (string) ($content['committee_name'] ?? '');
        $committeeId = $content['committee_id'] ?? null;

        return [
            'committee_name' => ObCommitteeFormatter::spCommitteeLabel($rawName),
            'chair_name' => CommitteeLookup::chairFor(
                is_numeric($committeeId) ? (int) $committeeId : null,
                $rawName,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function enrichCommitteeReportContent(array $content): array
    {
        $committeeId = $content['committee_id'] ?? null;
        $committeeName = (string) ($content['committee_name'] ?? '');

        $committee = CommitteeLookup::findById(is_numeric($committeeId) ? (int) $committeeId : null)
            ?? CommitteeLookup::findByName($committeeName);

        if ($committee !== null) {
            $content['committee_id'] = $committee->id;
            $content['committee_name'] = ObCommitteeFormatter::spCommitteeReportLabel($committee->name);
            $content['chair_name'] = CommitteeLookup::chairFor($committee->id, $committee->name);
            $content['needs_committee'] = false;
        }

        return $content;
    }
}
