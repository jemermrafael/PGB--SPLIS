<?php

namespace App\Services;

use App\Enums\ObBlockType;
use App\Models\AgendaItem;
use App\Models\AgendaObPlacement;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Support\ActivityLogger;
use App\Support\CommitteeLookup;
use App\Support\ObAgendaSnapshot;
use App\Support\ObBlockDefaults;
use App\Support\ObCommitteeFormatter;
use App\Support\ObRomanNumeral;
use App\Support\ObSectionPlacement;
use App\Support\ObTitleMarkup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ObDocumentService
{
    public function __construct(
        private AgendaObPlacementService $placements,
        private BoardMemberNotifier $boardMemberNotifier,
        private MunicipalNotifier $municipalNotifier,
        private ObCommitteeReportConsolidator $committeeReports,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function blocksPayload(ObDocument $document): array
    {
        return $document->blocks()
            ->with('agendaItem:id,tracking_no,title,committee_referred')
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

        if ($block->type === ObBlockType::UnassignedAgenda) {
            $content = ObAgendaSnapshot::enrichUnassignedRow($content, $block->agendaItem);
        }

        $section = $this->inferSectionFromBlock($block);

        return [
            'id' => $block->id,
            'type' => $block->type->value,
            'type_label' => $block->type->label(),
            'sort_order' => $block->sort_order,
            'content' => $content,
            'agenda_item_id' => $block->agenda_item_id,
            'preview' => $block->previewText(),
            'section' => $section,
            'section_label' => config('order_of_business.agenda_sections.'.$section, $section),
            'can_move_section' => $this->canMoveBlockToSection($block),
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
        $content ??= ObBlockDefaults::empty($type);
        $anchoredBySection = $afterBlockId === null;
        $afterBlockId ??= $this->sectionAnchorBlockId($document, $type, $content);
        $sortOrder = $this->nextSortOrder($document, $afterBlockId);

        $this->shiftBlocksDown($document, $sortOrder);

        $block = ObBlock::create([
            'ob_document_id' => $document->id,
            'type' => $type,
            'sort_order' => $sortOrder,
            'content' => $content,
            'agenda_item_id' => $agendaItemId,
        ]);

        if ($anchoredBySection && $type === ObBlockType::CommitteeReport) {
            $this->normalizeCommitteeReportSection($document);
            $block->refresh();
        }

        return $block;
    }

    /**
     * Keep section-bound rows inside their own section when the caller has no reference block.
     *
     * @param  array<string, mixed>  $content
     */
    protected function sectionAnchorBlockId(ObDocument $document, ObBlockType $type, array $content): ?int
    {
        $section = match ($type) {
            ObBlockType::CommitteeReport => 'committee_reports',
            ObBlockType::UnfinishedCommittee, ObBlockType::UnfinishedAgenda => 'unfinished',
            ObBlockType::ReadingAgenda => ($content['reading'] ?? '2nd') === '3rd'
                ? 'business_3rd'
                : 'business_2nd',
            ObBlockType::UnassignedAgenda => ($content['kind'] ?? 'regular') === 'urgent'
                ? 'unassigned_urgent'
                : 'unassigned_regular',
            default => null,
        };

        return $section === null
            ? null
            : ObSectionPlacement::insertAfterBlockId($document, $section);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public function updateBlock(ObBlock $block, array $content): ObBlock
    {
        $regroupUnfinished = false;

        if (array_key_exists('title_html', $content)) {
            $titleHtml = ObTitleMarkup::forTitle(
                is_string($content['title_html']) ? $content['title_html'] : null,
                is_string($content['title'] ?? null) ? $content['title'] : null,
            );

            if ($titleHtml === null) {
                unset($content['title_html']);
            } else {
                $content['title_html'] = $titleHtml;
            }
        }

        if ($block->type === ObBlockType::UnfinishedAgenda) {
            $regroupUnfinished = $this->unfinishedCommitteeKey($block->content ?? [])
                !== $this->unfinishedCommitteeKey($content);
        }

        if ($block->type === ObBlockType::CommitteeReport) {
            $content = $this->enrichCommitteeReportContent($content);
        }

        if ($block->type === ObBlockType::RomanSection) {
            $content = ObRomanNumeral::formatSectionContent($content);
            $content = $this->normalizeAppearanceGuestsContent($content);
        }

        $block->update(['content' => $content]);

        if ($block->type === ObBlockType::RomanSection) {
            $this->syncSessionGuestsFromAppearanceBlock($block->obDocument ?? $block->load('obDocument')->obDocument, $content);
        }

        if ($regroupUnfinished) {
            $this->regroupUnfinishedBusiness($block->obDocument);
        }

        if ($block->type === ObBlockType::CommitteeReport) {
            $document = $block->obDocument ?? $block->load('obDocument')->obDocument;
            if ($document !== null) {
                $this->normalizeCommitteeReportSection($document);
            }
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
            $this->reorderBlocks($document, array_merge($prefixIds, $suffixIds));

            return;
        }

        $groups = [];

        foreach ($agendas as $agendaBlock) {
            $key = $this->unfinishedCommitteeKey($agendaBlock->content ?? []);

            if (! array_key_exists($key, $groups)) {
                $groups[$key] = [];
            }

            $groups[$key][] = $agendaBlock;
        }

        foreach ($groups as &$items) {
            usort($items, fn (ObBlock $a, ObBlock $b): int => $this->agendaBlockSortKey($a) <=> $this->agendaBlockSortKey($b));
        }
        unset($items);

        uksort($groups, function (string $a, string $b) use ($groups): int {
            $byLowestAgenda = $this->agendaBlockSortKey($groups[$a][0]) <=> $this->agendaBlockSortKey($groups[$b][0]);

            return $byLowestAgenda !== 0 ? $byLowestAgenda : $a <=> $b;
        });

        $middleIds = [];

        foreach ($groups as $key => $items) {
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

    /**
     * Keep every agenda-bearing OB section in ascending Agenda No. order.
     */
    public function normalizeAgendaSections(ObDocument $document): void
    {
        $this->regroupUnfinishedBusiness($document);
        $this->normalizeCommitteeReportSection($document);

        foreach (['business_2nd', 'business_3rd', 'unassigned_urgent', 'unassigned_regular'] as $section) {
            $this->normalizeAgendaSection($document, $section);
        }
    }

    public function deleteBlock(ObBlock $block, ?int $userId = null, string $source = 'manual', bool $normalizeCommitteeReports = true): void
    {
        $block->loadMissing(['obDocument.legislativeSession', 'agendaItem']);
        $document = $block->obDocument;
        $deletedOrder = $block->sort_order;
        $agenda = $block->agendaItem;
        $session = $document?->legislativeSession;
        $section = $this->inferSectionFromBlock($block);
        $wasCommitteeReport = $block->type === ObBlockType::CommitteeReport;

        $block->delete();

        ObBlock::query()
            ->where('ob_document_id', $document->id)
            ->where('sort_order', '>', $deletedOrder)
            ->decrement('sort_order');

        if ($agenda && $session && $source !== 'relocation') {
            ActivityLogger::log('agenda.removed_from_ob', $agenda, ActivityLogger::agendaObProperties($agenda, [
                'source' => $source,
                'section' => $section,
                'section_label' => config('order_of_business.agenda_sections.'.$section, $section),
                'session_id' => $session->id,
                'session_title' => $session->displayTitle(),
                'session_date' => $session->session_date?->format('Y-m-d'),
            ]), $userId ?? auth()->id());
        }

        if ($wasCommitteeReport && $normalizeCommitteeReports && $document !== null) {
            $this->normalizeCommitteeReportSection($document);
        }
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

        $this->applyBlockOrder($document, $blockIds);
        $this->normalizeCommitteeReportSection($document);
    }

    /**
     * Sort IV. Committee Report rows by lowest agenda number, renumber row_no, and rename BM PDFs.
     */
    public function normalizeCommitteeReportSection(ObDocument $document): void
    {
        /** @var Collection<int, ObBlock> $blocks */
        $blocks = $document->blocks()->orderBy('sort_order')->get()->values();
        $reportBlocks = $blocks
            ->filter(fn (ObBlock $block) => $block->type === ObBlockType::CommitteeReport)
            ->values();

        if ($reportBlocks->isEmpty()) {
            return;
        }

        // Laravel Collection::sortBy([cb, cb]) can invert order when keys differ;
        // a single callback that returns [primary, tie-break] sorts correctly.
        $sortedReports = $reportBlocks
            ->sortBy(fn (ObBlock $block) => [
                $this->committeeReportSortKey($block),
                (int) $block->id,
            ])
            ->values();

        $sortedReportIds = $sortedReports->pluck('id')->map(fn ($id) => (int) $id)->all();
        $anchorIndex = ObSectionPlacement::committeeReportAnchorIndex($blocks);
        $newOrder = [];

        if ($anchorIndex !== null) {
            // Rows added without a section anchor land at the end of the document,
            // which prints an empty IV and a stray table after the closing.
            foreach ($blocks as $index => $block) {
                if ($block->type === ObBlockType::CommitteeReport) {
                    continue;
                }

                $newOrder[] = (int) $block->id;

                if ($index === $anchorIndex) {
                    array_push($newOrder, ...$sortedReportIds);
                }
            }
        } else {
            $reportSlot = 0;

            foreach ($blocks as $block) {
                if ($block->type === ObBlockType::CommitteeReport) {
                    $newOrder[] = $sortedReportIds[$reportSlot];
                    $reportSlot++;
                } else {
                    $newOrder[] = (int) $block->id;
                }
            }
        }

        $currentOrder = $blocks->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($newOrder !== $currentOrder) {
            $this->applyBlockOrder($document, $newOrder);
            $sortedReports = ObBlock::query()
                ->whereIn('id', $sortedReportIds)
                ->get()
                ->sortBy(fn (ObBlock $block) => array_search((int) $block->id, $sortedReportIds, true))
                ->values();
        }

        foreach ($sortedReports as $index => $block) {
            $rowNo = $index + 1;
            $content = $block->content ?? [];
            if ((int) ($content['row_no'] ?? 0) === $rowNo) {
                continue;
            }

            $content['row_no'] = $rowNo;
            $block->update(['content' => $content]);
        }

        app(BoardMemberCommitteeReportService::class)->syncObStyleFilenamesForDocument($document->fresh() ?? $document);
    }

    /**
     * @param  list<int>  $blockIds
     */
    protected function applyBlockOrder(ObDocument $document, array $blockIds): void
    {
        DB::transaction(function () use ($blockIds): void {
            foreach ($blockIds as $index => $blockId) {
                ObBlock::whereKey($blockId)->update(['sort_order' => $index + 1]);
            }
        });
    }

    protected function committeeReportSortKey(ObBlock $block): string
    {
        $nos = ObAgendaSnapshot::agendaNosFromContent($block->content ?? []);
        $numeric = [];

        foreach ($nos as $no) {
            $digits = preg_replace('/\D+/', '', trim((string) $no)) ?? '';
            if ($digits !== '' && ctype_digit($digits)) {
                $numeric[] = (int) $digits;
            }
        }

        if ($numeric !== []) {
            return str_pad((string) min($numeric), 10, '0', STR_PAD_LEFT);
        }

        if ($nos !== []) {
            return (string) min($nos);
        }

        return 'zzzz'.str_pad((string) $block->id, 10, '0', STR_PAD_LEFT);
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
        ?int $placedBy = null,
        string $source = 'manual',
    ): array {
        $items = AgendaItem::query()
            ->whereIn('id', $agendaItemIds)
            ->orderBy('date_received')
            ->orderBy('id')
            ->get();

        $items = $items->reject(fn (AgendaItem $item) => $item->status === AgendaItem::STATUS_DONE)->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'agenda_item_ids' => ['Done agenda items cannot be added to the Order of Business.'],
            ]);
        }

        $alreadyLinked = $this->linkedAgendaItemIds($document);

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

        $lastCommitteeReportBlock = null;
        if ($section === 'committee_reports' && $afterId !== null) {
            $previousBlock = ObBlock::query()->find($afterId);
            if ($previousBlock?->type === ObBlockType::CommitteeReport) {
                $lastCommitteeReportBlock = $previousBlock;
            }
        }

        foreach ($items->values() as $index => $item) {
            if ($section === 'unfinished') {
                $committee = ObCommitteeFormatter::resolvedLabel(null, $item->committee_referred);
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
                0,
                blank($item->committee_referred) ? $committeeId : null,
            );

            if ($section === 'committee_reports'
                && $lastCommitteeReportBlock !== null
                && ObAgendaSnapshot::committeeReportKey($lastCommitteeReportBlock->content ?? []) === ObAgendaSnapshot::committeeReportKey($content)) {
                $block = $this->mergeIntoCommitteeReportBlock($lastCommitteeReportBlock, $item);
                $created[] = $block;
                $this->placements->record($item, $block, $document, $section, $placedBy);

                continue;
            }

            if ($section === 'committee_reports') {
                $content['row_no'] = ++$rowNo;
                $content['agenda_item_ids'] = [$item->id];
            }

            $block = $this->addBlock(
                $document,
                $type,
                $content,
                $afterId,
                $item->id,
            );
            $created[] = $block;
            $afterId = $block->id;
            $this->placements->record($item, $block, $document, $section, $placedBy);

            if ($section === 'committee_reports') {
                $lastCommitteeReportBlock = $block;
            }
        }

        if ($section === 'committee_reports' && $this->committeeReports->consolidate($document) > 0) {
            $created = collect($created)
                ->filter(fn (ObBlock $block) => $block->exists && ObBlock::query()->whereKey($block->id)->exists())
                ->map(fn (ObBlock $block) => $block->fresh(['agendaItem']))
                ->filter()
                ->values()
                ->all();
        }

        $document->loadMissing('legislativeSession');
        $session = $document->legislativeSession;

        if ($session instanceof LegislativeSession) {
            $session->setRelation('obDocument', $document);

            if ($session->isNotifiableForObAgendaAdds()) {
                $this->boardMemberNotifier->notifyAgendasAddedToOb($items, $session);
                $this->municipalNotifier->notifyAgendasAddedToOb($items, $session);
            }
        }

        if (in_array($source, ['manual', 'manual_move'], true)) {
            AgendaItem::query()
                ->whereIn('id', $items->pluck('id'))
                ->update(['ob_manual_override_at' => now()]);

            $session = $document->legislativeSession;

            if ($session) {
                foreach ($items as $item) {
                    ActivityLogger::log('agenda.added_to_ob', $item, ActivityLogger::agendaObProperties($item, [
                        'source' => 'manual',
                        'section' => $section,
                        'section_label' => config('order_of_business.agenda_sections.'.$section, $section),
                        'session_id' => $session->id,
                        'session_title' => $session->displayTitle(),
                        'session_date' => $session->session_date?->format('Y-m-d'),
                    ]), $placedBy);
                }
            }
        }

        if ($section === 'committee_reports'
            || collect($created)->contains(fn (ObBlock $block) => $block->type === ObBlockType::CommitteeReport)) {
            $this->normalizeCommitteeReportSection($document);
        } elseif ($section === 'unfinished') {
            $this->regroupUnfinishedBusiness($document);
        } else {
            $this->normalizeAgendaSection($document, $section);
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
        $wasFinal = $document->isFinal();

        $document->update(collect($data)->only(['title', 'status'])->filter(fn ($v) => $v !== null)->all());

        $document = $document->fresh();

        if (! $wasFinal && $document->isFinal()) {
            $this->notifyLinkedAgendasForFinalDocument($document);
            $session = $document->legislativeSession;

            if ($session instanceof LegislativeSession) {
                $this->boardMemberNotifier->notifySessionCreated($session);
                $this->boardMemberNotifier->notifyObDocumentCreated($session, $document);
            }
        }

        return $document;
    }

    protected function notifyLinkedAgendasForFinalDocument(ObDocument $document): void
    {
        $document->loadMissing('legislativeSession');
        $session = $document->legislativeSession;

        if ($session === null) {
            return;
        }

        $session->setRelation('obDocument', $document);

        if (! $session->isNotifiableForObAgendaAdds()) {
            return;
        }

        $agendaIds = $this->linkedAgendaItemIds($document);

        if ($agendaIds->isEmpty()) {
            $agendaIds = AgendaObPlacement::query()
                ->where('ob_document_id', $document->id)
                ->pluck('agenda_item_id')
                ->unique()
                ->values();
        }

        if ($agendaIds->isEmpty()) {
            return;
        }

        AgendaItem::query()
            ->whereIn('id', $agendaIds)
            ->get()
            ->pipe(function ($items) use ($session): void {
                $this->boardMemberNotifier->notifyAgendasAddedToOb($items, $session);
                $this->municipalNotifier->notifyAgendasAddedToOb($items, $session);
            });
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
        $committeeId = $content['committee_id'] ?? null;

        if (is_numeric($committeeId) && (int) $committeeId > 0) {
            return 'id:'.(int) $committeeId;
        }

        return ObCommitteeFormatter::resolvedLabel(
            null,
            (string) ($content['committee_name'] ?? ''),
        );
    }

    protected function normalizeAgendaSection(ObDocument $document, string $section): void
    {
        /** @var Collection<int, ObBlock> $blocks */
        $blocks = $document->blocks()->orderBy('sort_order')->get()->values();
        $bounds = ObSectionPlacement::sectionBounds($blocks, $section);

        if ($bounds === null) {
            return;
        }

        [$startIndex, $endIndex] = $bounds;
        $matches = fn (ObBlock $block): bool => match ($section) {
            'business_2nd' => $block->type === ObBlockType::ReadingAgenda
                && ($block->content['reading'] ?? '2nd') !== '3rd',
            'business_3rd' => $block->type === ObBlockType::ReadingAgenda
                && ($block->content['reading'] ?? '2nd') === '3rd',
            'unassigned_urgent' => $block->type === ObBlockType::UnassignedAgenda
                && ($block->content['kind'] ?? 'regular') === 'urgent',
            'unassigned_regular' => $block->type === ObBlockType::UnassignedAgenda
                && ($block->content['kind'] ?? 'regular') !== 'urgent',
            default => false,
        };

        $slotIndexes = [];
        $sectionAgendas = collect();

        for ($index = $startIndex + 1; $index < $endIndex; $index++) {
            $block = $blocks[$index] ?? null;
            if ($block && $matches($block)) {
                $slotIndexes[] = $index;
                $sectionAgendas->push($block);
            }
        }

        if ($sectionAgendas->count() < 2) {
            return;
        }

        $sortedIds = $sectionAgendas
            ->sortBy(fn (ObBlock $block) => $this->agendaBlockSortKey($block))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
        $orderedIds = $blocks->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($slotIndexes as $offset => $slotIndex) {
            $orderedIds[$slotIndex] = $sortedIds[$offset];
        }

        $this->applyBlockOrder($document, $orderedIds);
    }

    /**
     * @return array{0: int, 1: int|string, 2: int}
     */
    protected function agendaBlockSortKey(ObBlock $block): array
    {
        $nos = ObAgendaSnapshot::sortAgendaNos(ObAgendaSnapshot::agendaNosFromContent($block->content ?? []));
        $no = trim((string) ($nos[0] ?? ''));

        if ($no !== '' && ctype_digit($no)) {
            return [0, (int) $no, (int) $block->id];
        }

        return [1, mb_strtolower($no), (int) $block->id];
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
            'committee_name' => ObCommitteeFormatter::resolvedLabel(
                is_numeric($committeeId) ? (int) $committeeId : null,
                $rawName,
            ),
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
        $agendaNo = (string) ($content['agenda_no'] ?? '');
        if ($agendaNo !== '' && str_contains($agendaNo, ',')) {
            $content['agenda_nos'] = array_values(array_filter(array_map('trim', explode(',', $agendaNo))));
            $content['agenda_no'] = $content['agenda_nos'][0] ?? $agendaNo;
        } elseif (! empty($content['agenda_nos']) && is_array($content['agenda_nos'])) {
            $content['agenda_nos'] = ObAgendaSnapshot::agendaNosFromContent($content);
            $content['agenda_no'] = $content['agenda_nos'][0] ?? ($content['agenda_no'] ?? '');
        }

        return ObAgendaSnapshot::enrichCommitteeReportRow($content);
    }

    protected function mergeIntoCommitteeReportBlock(ObBlock $block, AgendaItem $item): ObBlock
    {
        $content = ObAgendaSnapshot::mergeCommitteeReportRows(
            $block->content ?? [],
            ObAgendaSnapshot::committeeReport($item, (int) ($block->content['row_no'] ?? 0)),
        );

        $ids = $content['agenda_item_ids'] ?? ($block->agenda_item_id ? [$block->agenda_item_id] : []);
        $ids[] = $item->id;
        $content['agenda_item_ids'] = array_values(array_unique($ids));

        $block->update(['content' => $content]);

        return $block->fresh(['agendaItem']);
    }

    public function documentContainsAgenda(ObDocument $document, int $agendaItemId): bool
    {
        return $this->linkedAgendaItemIds($document)->contains($agendaItemId);
    }

    public function sectionForAgendaInDocument(ObDocument $document, int $agendaItemId): ?string
    {
        $block = $this->findPrimaryBlockForAgenda($document, $agendaItemId);

        return $block ? $this->inferSectionFromBlock($block) : null;
    }

    public function moveAgendaBlockToSection(ObBlock $block, string $targetSection, ?int $userId = null): ObDocument
    {
        if (! $this->canMoveBlockToSection($block)) {
            throw ValidationException::withMessages([
                'section' => ['This block cannot be moved between sections.'],
            ]);
        }

        $agendaIds = $this->agendaIdsFromBlock($block);
        $agenda = AgendaItem::query()->findOrFail($agendaIds[0]);
        $document = $block->obDocument;
        $currentSection = $this->inferSectionFromBlock($block);

        if ($currentSection === $targetSection) {
            throw ValidationException::withMessages([
                'section' => ['This agenda is already in that section.'],
            ]);
        }

        $committeeId = isset($block->content['committee_id']) && is_numeric($block->content['committee_id'])
            ? (int) $block->content['committee_id']
            : null;

        DB::transaction(function () use ($document, $agenda, $currentSection, $targetSection, $committeeId, $userId): void {
            $this->removeAgendaFromDocument($document, $agenda, $userId, 'relocation');
            $this->addAgendaItems(
                $document,
                [$agenda->id],
                null,
                $targetSection,
                $committeeId,
                $userId,
                'manual_move',
            );

            $document->loadMissing('legislativeSession');
            $session = $document->legislativeSession;

            if ($session) {
                ActivityLogger::log('agenda.ob_relocated', $agenda, ActivityLogger::agendaObProperties($agenda, [
                    'source' => 'manual',
                    'from_section' => $currentSection,
                    'from_section_label' => config('order_of_business.agenda_sections.'.$currentSection, $currentSection),
                    'to_section' => $targetSection,
                    'to_section_label' => config('order_of_business.agenda_sections.'.$targetSection, $targetSection),
                    'session_id' => $session->id,
                    'session_title' => $session->displayTitle(),
                    'session_date' => $session->session_date?->format('Y-m-d'),
                ]), $userId);
            }
        });

        return $document->fresh();
    }

    public function canMoveBlockToSection(ObBlock $block): bool
    {
        if (count($this->agendaIdsFromBlock($block)) !== 1) {
            return false;
        }

        $type = $block->type instanceof ObBlockType ? $block->type : ObBlockType::from((string) $block->type);

        return in_array($type, [
            ObBlockType::UnfinishedAgenda,
            ObBlockType::UnassignedAgenda,
            ObBlockType::ReadingAgenda,
            ObBlockType::CommitteeReport,
        ], true);
    }

    /**
     * @return list<int>
     */
    protected function agendaIdsFromBlock(ObBlock $block): array
    {
        $ids = [];

        if ($block->agenda_item_id !== null) {
            $ids[] = (int) $block->agenda_item_id;
        }

        foreach ($block->content['agenda_item_ids'] ?? [] as $id) {
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    public function removeAgendaFromDocument(
        ObDocument $document,
        AgendaItem $agenda,
        ?int $userId = null,
        string $source = 'automatic',
    ): void {
        $blocks = $this->findBlocksForAgenda($document, $agenda->id);
        $touchedCommitteeReport = false;

        foreach ($blocks as $block) {
            if ($block->type === ObBlockType::CommitteeReport) {
                $touchedCommitteeReport = true;
                $ids = collect($block->content['agenda_item_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0)
                    ->unique()
                    ->values();

                if ($block->agenda_item_id !== null) {
                    $ids = $ids->push((int) $block->agenda_item_id)->unique()->values();
                }

                $remaining = $ids->reject(fn (int $id) => $id === $agenda->id)->values();

                if ($remaining->isEmpty()) {
                    $this->deleteBlock($block, $userId, $source, normalizeCommitteeReports: false);
                } else {
                    $content = $block->content ?? [];
                    $content['agenda_item_ids'] = $remaining->all();
                    $block->update(['content' => $content]);
                    $this->logAgendaRemovedFromOb($agenda, $document, $this->inferSectionFromBlock($block), $source, $userId);
                }

                continue;
            }

            $this->deleteBlock($block, $userId, $source);
        }

        if ($touchedCommitteeReport) {
            $this->normalizeCommitteeReportSection($document);
        }
    }

    protected function findPrimaryBlockForAgenda(ObDocument $document, int $agendaItemId): ?ObBlock
    {
        return $this->findBlocksForAgenda($document, $agendaItemId)->first();
    }

    /**
     * @return Collection<int, ObBlock>
     */
    protected function findBlocksForAgenda(ObDocument $document, int $agendaItemId): Collection
    {
        return ObBlock::query()
            ->where('ob_document_id', $document->id)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (ObBlock $block) => $this->blockContainsAgenda($block, $agendaItemId))
            ->values();
    }

    protected function blockContainsAgenda(ObBlock $block, int $agendaItemId): bool
    {
        if ((int) $block->agenda_item_id === $agendaItemId) {
            return true;
        }

        foreach ($block->content['agenda_item_ids'] ?? [] as $id) {
            if ((int) $id === $agendaItemId) {
                return true;
            }
        }

        return false;
    }

    protected function logAgendaRemovedFromOb(
        AgendaItem $agenda,
        ObDocument $document,
        string $section,
        string $source,
        ?int $userId,
    ): void {
        if ($source === 'relocation') {
            return;
        }

        $document->loadMissing('legislativeSession');
        $session = $document->legislativeSession;

        if (! $session) {
            return;
        }

        ActivityLogger::log('agenda.removed_from_ob', $agenda, ActivityLogger::agendaObProperties($agenda, [
            'source' => $source,
            'section' => $section,
            'section_label' => config('order_of_business.agenda_sections.'.$section, $section),
            'session_id' => $session->id,
            'session_title' => $session->displayTitle(),
            'session_date' => $session->session_date?->format('Y-m-d'),
        ]), $userId);
    }

    /**
     * @return Collection<int, int>
     */
    protected function linkedAgendaItemIds(ObDocument $document): Collection
    {
        return ObBlock::query()
            ->where('ob_document_id', $document->id)
            ->get()
            ->flatMap(function (ObBlock $block): array {
                $ids = [];

                if ($block->agenda_item_id !== null) {
                    $ids[] = (int) $block->agenda_item_id;
                }

                foreach ($block->content['agenda_item_ids'] ?? [] as $id) {
                    if (is_numeric($id)) {
                        $ids[] = (int) $id;
                    }
                }

                return $ids;
            })
            ->unique()
            ->values();
    }

    protected function inferSectionFromBlock(ObBlock $block): string
    {
        $type = $block->type instanceof ObBlockType ? $block->type->value : (string) $block->type;

        return match ($type) {
            ObBlockType::CommitteeReport->value => 'committee_reports',
            ObBlockType::UnfinishedAgenda->value, ObBlockType::UnfinishedCommittee->value => 'unfinished',
            ObBlockType::ReadingAgenda->value => ($block->content['reading'] ?? '2nd') === '3rd'
                ? 'business_3rd'
                : 'business_2nd',
            ObBlockType::UnassignedAgenda->value => ($block->content['kind'] ?? 'regular') === 'urgent'
                ? 'unassigned_urgent'
                : 'unassigned_regular',
            default => 'unassigned_regular',
        };
    }

    /**
     * Keep empty guest rows in the editor so "Add guest" fields do not vanish after autosave.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function normalizeAppearanceGuestsContent(array $content): array
    {
        if (! $this->isAppearanceGuestsSection($content)) {
            return $content;
        }

        $guests = collect($content['guests'] ?? [])
            ->filter(fn ($guest) => is_array($guest))
            ->map(fn (array $guest) => [
                'name' => (string) ($guest['name'] ?? ''),
            ])
            ->values()
            ->all();

        $content['guests'] = $guests;

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function isAppearanceGuestsSection(array $content): bool
    {
        $title = mb_strtoupper(trim((string) ($content['title'] ?? '')));
        $numeral = ObRomanNumeral::normalize((string) ($content['numeral'] ?? ''));

        return $numeral === 'II' && str_contains($title, 'APPEARANCE');
    }

    /**
     * Push Section II guest names onto the session attendance guests list (preserving remarks / extras).
     *
     * @param  array<string, mixed>  $content
     */
    public function syncSessionGuestsFromAppearanceBlock(?ObDocument $document, array $content): void
    {
        if ($document === null || ! $this->isAppearanceGuestsSection($content)) {
            return;
        }

        $document->loadMissing('legislativeSession');
        $session = $document->legislativeSession;

        if ($session === null) {
            return;
        }

        $this->mergeAppearanceGuestsIntoSession($session, $content['guests'] ?? []);
    }

    /**
     * Ensure attendance guests include names from OB Section II Appearance of Guest/s.
     */
    public function syncSessionGuestsFromDocument(LegislativeSession $session): void
    {
        $session->loadMissing('obDocument.blocks');
        $document = $session->obDocument;

        if ($document === null) {
            return;
        }

        $block = $this->appearanceGuestsBlock($document);

        if ($block === null) {
            return;
        }

        $this->mergeAppearanceGuestsIntoSession($session, $block->content['guests'] ?? []);
    }

    /**
     * Save attendance guests and mirror removals of official OB Section II names back to the Maker.
     *
     * @param  list<array{name?: string, remarks?: string, source?: string}>  $submittedGuests
     */
    public function applyAttendanceGuestsUpdate(LegislativeSession $session, array $submittedGuests): void
    {
        $session->loadMissing('obDocument.blocks');

        $normalized = collect($submittedGuests)
            ->filter(fn ($guest) => is_array($guest))
            ->map(fn (array $guest) => [
                'name' => trim((string) ($guest['name'] ?? '')),
                'remarks' => trim((string) ($guest['remarks'] ?? '')),
            ])
            ->filter(fn (array $guest) => $guest['name'] !== '' || $guest['remarks'] !== '')
            ->values();

        $submittedNames = $normalized
            ->map(fn (array $guest) => mb_strtolower($guest['name']))
            ->filter(fn (string $name) => $name !== '')
            ->unique()
            ->values()
            ->all();

        $block = $session->obDocument
            ? $this->appearanceGuestsBlock($session->obDocument)
            : null;

        $obNamesBefore = $block === null
            ? []
            : collect($block->content['guests'] ?? [])
                ->filter(fn ($guest) => is_array($guest))
                ->map(fn (array $guest) => trim((string) ($guest['name'] ?? '')))
                ->filter(fn (string $name) => $name !== '')
                ->values();

        $obNamesBeforeKeys = $obNamesBefore
            ->map(fn (string $name) => mb_strtolower($name))
            ->all();

        $removedOfficialNames = $obNamesBefore
            ->reject(fn (string $name) => in_array(mb_strtolower($name), $submittedNames, true))
            ->values();

        if ($block !== null && $removedOfficialNames->isNotEmpty()) {
            $content = $block->content ?? [];
            $content['guests'] = collect($content['guests'] ?? [])
                ->filter(fn ($guest) => is_array($guest))
                ->reject(function (array $guest) use ($removedOfficialNames): bool {
                    $name = mb_strtolower(trim((string) ($guest['name'] ?? '')));

                    return $removedOfficialNames
                        ->map(fn (string $removed) => mb_strtolower($removed))
                        ->contains($name);
                })
                ->values()
                ->all();

            $block->update(['content' => $this->normalizeAppearanceGuestsContent($content)]);
            $block->refresh();
        }

        $obNamesAfter = $block === null
            ? $obNamesBeforeKeys
            : collect($block->content['guests'] ?? [])
                ->filter(fn ($guest) => is_array($guest))
                ->map(fn (array $guest) => mb_strtolower(trim((string) ($guest['name'] ?? ''))))
                ->filter(fn (string $name) => $name !== '')
                ->unique()
                ->values()
                ->all();

        $guests = $normalized
            ->filter(fn (array $guest) => $guest['name'] !== '')
            ->unique(fn (array $guest) => mb_strtolower($guest['name']))
            ->map(fn (array $guest) => [
                'name' => $guest['name'],
                'remarks' => $guest['remarks'],
                'source' => in_array(mb_strtolower($guest['name']), $obNamesAfter, true)
                    ? 'ob'
                    : 'attendance',
            ])
            ->values()
            ->all();

        $session->forceFill([
            'guests' => $guests !== [] ? $guests : null,
        ])->save();
    }

    /**
     * @param  list<mixed>  $appearanceGuests
     */
    protected function mergeAppearanceGuestsIntoSession(LegislativeSession $session, array $appearanceGuests): void
    {
        $existing = collect($session->guests ?? [])
            ->filter(fn ($guest) => is_array($guest))
            ->values();

        $existingByName = $existing
            ->filter(fn (array $guest) => filled($guest['name'] ?? null))
            ->keyBy(fn (array $guest) => mb_strtolower(trim((string) $guest['name'])));

        $fromOb = collect($appearanceGuests)
            ->filter(fn ($guest) => is_array($guest))
            ->map(fn (array $guest) => trim((string) ($guest['name'] ?? '')))
            ->filter(fn (string $name) => $name !== '')
            ->unique(fn (string $name) => mb_strtolower($name))
            ->values()
            ->map(function (string $name) use ($existingByName): array {
                $prior = $existingByName->get(mb_strtolower($name));

                return [
                    'name' => $name,
                    'remarks' => trim((string) ($prior['remarks'] ?? '')),
                    'source' => 'ob',
                ];
            });

        $obNames = $fromOb
            ->map(fn (array $guest) => mb_strtolower($guest['name']))
            ->all();

        $referenceNames = $existing
            ->map(fn (array $guest) => mb_strtolower(trim((string) ($guest['name'] ?? ''))))
            ->filter(fn (string $name) => $name !== '')
            ->merge($obNames)
            ->unique()
            ->values()
            ->all();

        // Attendance-only guests are informational. Former OB names (source=ob) that
        // are no longer on Section II are dropped so the official list stays in sync.
        $extras = $existing
            ->filter(function (array $guest) use ($obNames, $referenceNames): bool {
                $name = trim((string) ($guest['name'] ?? ''));
                if ($name === '') {
                    return false;
                }

                $key = mb_strtolower($name);
                if (in_array($key, $obNames, true)) {
                    return false;
                }

                $source = (string) ($guest['source'] ?? '');
                if ($source === 'ob') {
                    return false;
                }

                $remarks = trim((string) ($guest['remarks'] ?? ''));
                if ($remarks === '' && $this->isIncompleteGuestNamePrefix($key, $referenceNames)) {
                    return false;
                }

                return true;
            })
            ->map(fn (array $guest) => [
                'name' => trim((string) ($guest['name'] ?? '')),
                'remarks' => trim((string) ($guest['remarks'] ?? '')),
                'source' => 'attendance',
            ])
            ->values();

        $merged = $fromOb->concat($extras)->values()->all();

        $session->forceFill([
            'guests' => $merged !== [] ? $merged : null,
        ])->save();
    }

    protected function appearanceGuestsBlock(ObDocument $document): ?ObBlock
    {
        return $document->blocks
            ->first(function (ObBlock $block): bool {
                if ($block->type !== ObBlockType::RomanSection) {
                    return false;
                }

                return $this->isAppearanceGuestsSection($block->content ?? []);
            });
    }

    /**
     * @param  list<string>  $referenceNames  Lowercased names.
     */
    protected function isIncompleteGuestNamePrefix(string $candidate, array $referenceNames): bool
    {
        foreach ($referenceNames as $reference) {
            if ($reference !== $candidate && str_starts_with($reference, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
