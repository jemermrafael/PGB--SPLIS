<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\IncomingDocument;
use Illuminate\Support\Facades\DB;

class AgendaLinkService
{
    public function __construct(
        protected IncomingDocumentLinker $incomingLinker,
    ) {}

    public function unlinkIncoming(AgendaItem $agenda): void
    {
        if (! $agenda->hasIncoming()) {
            throw new \RuntimeException('This agenda item is not linked to an incoming record.');
        }

        $incoming = $agenda->incomingDocument;
        if (! $incoming) {
            $agenda->update(['incoming_document_id' => null]);

            return;
        }

        if ($incoming->isLinked()) {
            throw new \RuntimeException('Unlink the resolution first before removing the incoming link.');
        }

        DB::transaction(function () use ($agenda, $incoming) {
            $agenda->update(['incoming_document_id' => null]);

            if ($incoming->source === IncomingDocument::SOURCE_AGENDA) {
                $incoming->delete();
            } else {
                $incoming->update(['agenda_item_id' => null]);
            }
        });
    }

    public function unlinkResolution(AgendaItem $agenda): void
    {
        if (! $agenda->resolution_id) {
            throw new \RuntimeException('This agenda item is not linked to a resolution.');
        }

        $resolution = $agenda->resolution;
        $incoming = $agenda->incomingDocument;

        DB::transaction(function () use ($agenda, $resolution, $incoming) {
            $agenda->update(['resolution_id' => null]);

        if ($incoming && $resolution && $incoming->resolution_id === $resolution->id) {
                if ($incoming->isLinked()) {
                    $this->incomingLinker->unlink($incoming);
                } else {
                    $incoming->update(['resolution_id' => null]);
                }
            }
        });
    }
}
