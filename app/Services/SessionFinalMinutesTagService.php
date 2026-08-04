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
        $this->syncSharedPdfTags(
            session: $session,
            selectedIds: $selectedIds,
            relation: 'finalMinutesAgendaItems',
            sessionPathColumn: 'pdf_final_minutes_path',
            agendaPathColumn: 'minutes_pdf_path',
        );
    }

    /**
     * @param  list<int|string>  $selectedIds
     */
    public function syncFinalJournalTags(
        LegislativeSession $session,
        array $selectedIds,
        ?int $userId = null,
    ): void {
        $this->syncSharedPdfTags(
            session: $session,
            selectedIds: $selectedIds,
            relation: 'finalJournalAgendaItems',
            sessionPathColumn: 'pdf_final_journal_path',
            agendaPathColumn: 'journal_pdf_path',
        );
    }

    /**
     * @param  list<int|string>  $selectedIds
     */
    protected function syncSharedPdfTags(
        LegislativeSession $session,
        array $selectedIds,
        string $relation,
        string $sessionPathColumn,
        string $agendaPathColumn,
    ): void {
        $candidates = $this->committeeReportAgendaIdsForSession($session)->all();

        $selected = collect($selectedIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && in_array($id, $candidates, true))
            ->unique()
            ->values();

        DB::transaction(function () use ($session, $selected, $relation, $sessionPathColumn, $agendaPathColumn): void {
            $session->{$relation}()->sync($selected->all());

            $sessionPath = filled($session->{$sessionPathColumn})
                ? (string) $session->{$sessionPathColumn}
                : null;

            if ($sessionPath === null) {
                return;
            }

            $toClear = AgendaItem::query()
                ->where($agendaPathColumn, $sessionPath)
                ->whereNotIn('id', $selected->all())
                ->get();

            foreach ($toClear as $agenda) {
                $agenda->forceFill([$agendaPathColumn => null])->saveQuietly();
            }

            $toApply = AgendaItem::query()
                ->whereIn('id', $selected->all())
                ->get();

            foreach ($toApply as $agenda) {
                if ((string) $agenda->{$agendaPathColumn} === $sessionPath) {
                    continue;
                }
                $agenda->forceFill([$agendaPathColumn => $sessionPath])->saveQuietly();
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
