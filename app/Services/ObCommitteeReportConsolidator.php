<?php

namespace App\Services;

use App\Enums\ObBlockType;
use App\Models\AgendaItem;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Support\ObAgendaSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One committee belongs on one row under IV. Committee Report.
 *
 * Agendas for the same committee (whether tagged on one shared PDF or uploaded
 * as separate reports) can reach the document as separate blocks; this folds
 * those blocks into the first one. Link rendering keeps a single shared link
 * when every agenda uses the same PDF, or per-number links when PDFs differ.
 */
class ObCommitteeReportConsolidator
{
    public function __construct(
        protected AgendaObPlacementService $placements,
    ) {}

    /**
     * @return int Blocks absorbed into an earlier block.
     */
    public function consolidate(ObDocument $document): int
    {
        $blocks = $this->committeeReportBlocks($document);

        if ($blocks->isEmpty()) {
            return 0;
        }

        $absorbedCount = DB::transaction(function () use ($document, $blocks): int {
            $primaryByCommittee = [];
            $absorbed = [];

            foreach ($blocks as $block) {
                $committeeKey = $this->committeeKey($block);

                if ($committeeKey === null) {
                    continue;
                }

                if (! isset($primaryByCommittee[$committeeKey])) {
                    $primaryByCommittee[$committeeKey] = $block;

                    continue;
                }

                $this->absorb($primaryByCommittee[$committeeKey], $block, $document);
                $absorbed[] = $block->id;
            }

            if ($absorbed !== []) {
                ObBlock::query()->whereIn('id', $absorbed)->delete();
                $this->compactSortOrders($document);
            }

            return count($absorbed);
        });

        // Normalize after commit so row sorting sees the merged JSON content.
        app(ObDocumentService::class)->normalizeCommitteeReportSection($document);

        return $absorbedCount;
    }

    /**
     * @return Collection<int, ObBlock>
     */
    protected function committeeReportBlocks(ObDocument $document): Collection
    {
        return ObBlock::query()
            ->where('ob_document_id', $document->id)
            ->where('type', ObBlockType::CommitteeReport)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    protected function absorb(ObBlock $primary, ObBlock $redundant, ObDocument $document): void
    {
        $ids = array_values(array_unique(array_merge(
            $this->agendaIds($primary),
            $this->agendaIds($redundant),
        )));

        $content = ObAgendaSnapshot::mergeCommitteeReportRows(
            $primary->content ?? [],
            $redundant->content ?? [],
        );
        $content['agenda_item_ids'] = $ids;

        $primary->update(['content' => $content]);

        // Re-record every merged agenda onto the surviving block before the
        // redundant block (and its cascaded placements) is deleted.
        $agendas = AgendaItem::query()->whereIn('id', $ids)->get();

        foreach ($agendas as $agenda) {
            $this->placements->record($agenda, $primary, $document, 'committee_reports');
        }
    }

    protected function committeeKey(ObBlock $block): ?string
    {
        $content = is_array($block->content) ? $block->content : [];
        $key = ObAgendaSnapshot::committeeReportKey($content);

        if ($key === 'name:' || $key === 'id:0') {
            return null;
        }

        return $key;
    }

    protected function compactSortOrders(ObDocument $document): void
    {
        $ids = ObBlock::query()
            ->where('ob_document_id', $document->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        foreach ($ids as $index => $id) {
            ObBlock::whereKey($id)->update(['sort_order' => $index + 1]);
        }
    }

    /**
     * @return list<int>
     */
    protected function agendaIds(ObBlock $block): array
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

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }
}
