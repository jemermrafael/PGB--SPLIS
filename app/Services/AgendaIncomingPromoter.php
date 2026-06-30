<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\IncomingDocument;
use App\Models\Resolution;
use Illuminate\Support\Facades\DB;

class AgendaIncomingPromoter
{
    public function promote(AgendaItem $agenda, ?int $userId = null): IncomingDocument
    {
        if ($agenda->hasIncoming()) {
            throw new \RuntimeException('This agenda item already has an incoming record.');
        }

        return DB::transaction(function () use ($agenda, $userId) {
            $incoming = IncomingDocument::create([
                'agenda_item_id' => $agenda->id,
                'source' => IncomingDocument::SOURCE_AGENDA,
                'link_status' => IncomingDocument::LINK_UNLINKED,
                'resolution_id' => $agenda->resolution_id,
                'date_received' => $agenda->date_received,
                'municipality' => $agenda->sender,
                'title' => $agenda->title,
                'action_taken' => $agenda->outcome,
                'referral' => $agenda->committee_referred,
                'workflow_status' => config('agenda.statuses.'.$agenda->status, $agenda->status),
                'sp_res_no' => $agenda->reso_ord_ao_no,
                'sp_series' => $agenda->reso_ord_ao_series,
                'sp_title' => $agenda->resolution_title,
                'sp_date_approved' => $agenda->date_passed ?? $agenda->date_signed_by_gov,
                'remarks' => $agenda->remarks,
                'mun_pdf_url' => $agenda->request_pdf_url,
                'sp_pdf_url' => $agenda->reso_ord_ao_url,
                'created_by' => $userId,
            ]);

            $agenda->update(['incoming_document_id' => $incoming->id]);

            if ($agenda->reso_ord_ao_no && $agenda->reso_ord_ao_series && ! $agenda->resolution_id) {
                $resolution = Resolution::query()
                    ->where('resolution_no', $agenda->reso_ord_ao_no)
                    ->where('series', $agenda->reso_ord_ao_series)
                    ->first();

                if ($resolution) {
                    $agenda->update(['resolution_id' => $resolution->id]);
                }
            }

            return $incoming->fresh();
        });
    }
}
