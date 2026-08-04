<?php

namespace App\Services;

use App\Enums\ObBlockType;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SessionFinalMinutesTagService
{
    /**
     * Agenda IDs linked on this session’s OB via IV. Committee Report blocks.
     *
     * @return Collection<int, int>
     */
    public function committeeReportAgendaIdsForSession(LegislativeSession $session): Collection
    {
        $document = $session->relationLoaded('obDocument')
            ? $session->obDocument
            : $session->obDocument()->first();

        if ($document === null) {
            return collect();
        }

        $blocks = $document->relationLoaded('blocks')
            ? $document->blocks
            : $document->blocks()->get();

        return $blocks
            ->filter(function (ObBlock $block): bool {
                $type = $block->type instanceof ObBlockType
                    ? $block->type
                    : ObBlockType::tryFrom((string) $block->type);

                return $type === ObBlockType::CommitteeReport;
            })
            ->flatMap(fn (ObBlock $block) => $this->agendaIdsFromBlock($block))
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, AgendaItem>
     */
    public function committeeReportAgendasForSession(LegislativeSession $session): Collection
    {
        $ids = $this->committeeReportAgendaIdsForSession($session);

        if ($ids->isEmpty()) {
            return collect();
        }

        return AgendaItem::query()
            ->whereIn('id', $ids->all())
            ->orderBy('tracking_no')
            ->orderBy('id')
            ->get(['id', 'tracking_no', 'title']);
    }

    /**
     * @param  list<int|string>  $selectedIds
     */
    public function syncFinalMinutesTags(
        LegislativeSession $session,
        array $selectedIds,
        ?int $userId = null,
    ): void {
        $candidates = $this->committeeReportAgendaIdsForSession($session)->all();

        $selected = collect($selectedIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && in_array($id, $candidates, true))
            ->unique()
            ->values();

        DB::transaction(function () use ($session, $selected): void {
            $session->finalMinutesAgendaItems()->sync($selected->all());

            $sessionPath = filled($session->pdf_final_minutes_path)
                ? (string) $session->pdf_final_minutes_path
                : null;

            if ($sessionPath === null) {
                return;
            }

            $toClear = AgendaItem::query()
                ->where('minutes_pdf_path', $sessionPath)
                ->whereNotIn('id', $selected->all())
                ->get();

            foreach ($toClear as $agenda) {
                $agenda->forceFill(['minutes_pdf_path' => null])->saveQuietly();
            }

            $toApply = AgendaItem::query()
                ->whereIn('id', $selected->all())
                ->get();

            foreach ($toApply as $agenda) {
                if ((string) $agenda->minutes_pdf_path === $sessionPath) {
                    continue;
                }
                $agenda->forceFill(['minutes_pdf_path' => $sessionPath])->saveQuietly();
            }
        });
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
}
