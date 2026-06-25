<?php

namespace App\Services;

use App\Models\IncomingDocument;
use App\Models\Resolution;
use Illuminate\Support\Facades\DB;

class IncomingDocumentLinker
{
    public function link(IncomingDocument $incoming, Resolution $resolution): void
    {
        if ($incoming->isLinked()) {
            throw new \RuntimeException('This incoming document is already linked to a resolution.');
        }

        if ($resolution->incoming_document_id !== null) {
            throw new \RuntimeException('This resolution is already linked to an incoming document.');
        }

        DB::transaction(function () use ($incoming, $resolution) {
            $incoming->update([
                'resolution_id' => $resolution->id,
                'link_status' => IncomingDocument::LINK_LINKED,
            ]);

            $resolution->update([
                'incoming_document_id' => $incoming->id,
            ]);
        });
    }
}
