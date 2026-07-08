<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\AgendaObPlacement;
use App\Models\ObBlock;
use App\Models\ObDocument;
use Illuminate\Support\Facades\Auth;

class AgendaObPlacementService
{
    public function record(
        AgendaItem $agenda,
        ObBlock $block,
        ObDocument $document,
        string $section,
        ?int $userId = null,
    ): AgendaObPlacement {
        $content = $block->content ?? [];
        $sessionAgendaNo = $content['session_agenda_no']
            ?? $content['agenda_no']
            ?? null;

        return AgendaObPlacement::updateOrCreate(
            [
                'ob_block_id' => $block->id,
                'agenda_item_id' => $agenda->id,
            ],
            [
                'agenda_item_version_id' => $agenda->versions()->orderByDesc('version_no')->value('id'),
                'legislative_session_id' => $document->legislative_session_id,
                'ob_document_id' => $document->id,
                'section' => $section,
                'section_label' => config('order_of_business.agenda_sections.'.$section, $section),
                'session_agenda_no' => $sessionAgendaNo !== null ? (string) $sessionAgendaNo : null,
                'placed_by' => $userId ?? Auth::id(),
            ],
        );
    }
}
