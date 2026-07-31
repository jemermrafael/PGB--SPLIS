<?php

namespace App\Services;

use App\Enums\ObBlockType;
use App\Models\AgendaItem;
use App\Models\BoardMemberCommitteeReport;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Support\ObAgendaSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One committee report belongs on one row under IV. Committee Report. Agendas filed
 * under the same report can reach the document at different times, which leaves a
 * block per agenda; this folds those blocks back into the first one.
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
            $reportIds = $this->reportIdsByAgendaId($blocks);
            $primaryByReport = [];
            $absorbed = [];

            foreach ($blocks as $block) {
                $reportId = $this->soleReportId($block, $reportIds);

                if ($reportId === null) {
                    continue;
                }

                if (! isset($primaryByReport[$reportId])) {
                    $primaryByReport[$reportId] = $block;

                    continue;
                }

                $this->absorb($primaryByReport[$reportId], $block, $document);
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

    /**
     * Null when the block has no filed report, or spans more than one.
     *
     * @param  Collection<int, int>  $reportIds
     */
    protected function soleReportId(ObBlock $block, Collection $reportIds): ?int
    {
        $found = [];

        foreach ($this->agendaIds($block) as $agendaId) {
            $reportId = $reportIds->get($agendaId);

            if ($reportId !== null) {
                $found[(int) $reportId] = true;
            }
        }

        return count($found) === 1 ? array_key_first($found) : null;
    }

    /**
     * @param  Collection<int, ObBlock>  $blocks
     * @return Collection<int, int> Agenda item id => committee report id
     */
    protected function reportIdsByAgendaId(Collection $blocks): Collection
    {
        $agendaIds = $blocks->flatMap(fn (ObBlock $block) => $this->agendaIds($block))->unique()->values();

        if ($agendaIds->isEmpty()) {
            return collect();
        }

        $reports = BoardMemberCommitteeReport::query()
            ->whereHas('agendaItems', fn ($query) => $query->whereIn('agenda_items.id', $agendaIds->all()))
            ->with('agendaItems:id')
            ->orderBy('id')
            ->get();

        $map = collect();

        foreach ($reports as $report) {
            foreach ($report->agendaItems as $agenda) {
                if (! $map->has((int) $agenda->id)) {
                    $map->put((int) $agenda->id, (int) $report->id);
                }
            }
        }

        return $map;
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
