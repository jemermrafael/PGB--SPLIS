<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\AppropriationOrdinance;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Support\AgendaMeasureType;
use App\Support\DocumentType;
use App\Support\OrdinanceNumberParser;
use Illuminate\Support\Str;

class AgendaOutputPublisher
{
    public function __construct(
        protected AgendaOutputLinker $linker,
    ) {}

    public function publishIfDone(AgendaItem $agenda, ?int $userId = null): bool
    {
        if ($agenda->status !== AgendaItem::STATUS_DONE) {
            return false;
        }

        if ($agenda->isPublished()) {
            if ($this->syncPublishedOutput($agenda, $userId)) {
                return true;
            }
        }

        if ($this->linker->linkExistingIfPossible($agenda)) {
            $agenda->refresh();

            return $this->syncPublishedOutput($agenda, $userId);
        }

        $measureType = $agenda->reso_ord_ao_type
            ?? AgendaItem::inferMeasureType($agenda->resolution_title ?? $agenda->title);

        if ($measureType === null) {
            return false;
        }

        return match ($measureType) {
            AgendaMeasureType::RESOLUTION => $this->publishResolution($agenda, $userId),
            AgendaMeasureType::ORDINANCE => $this->publishOrdinance($agenda, $userId),
            AgendaMeasureType::APPROPRIATION_ORDINANCE => $this->publishAppropriationOrdinance($agenda, $userId),
            default => false,
        };
    }

    public function syncPublishedOutput(AgendaItem $agenda, ?int $userId = null): bool
    {
        if ($agenda->resolution_id && $agenda->resolution) {
            return $this->syncResolution($agenda);
        }

        if ($agenda->ordinance_id && $agenda->ordinance) {
            return $this->syncOrdinance($agenda);
        }

        if ($agenda->appropriation_ordinance_id && $agenda->appropriationOrdinance) {
            return $this->syncAppropriationOrdinance($agenda);
        }

        return false;
    }

    protected function syncResolution(AgendaItem $agenda): bool
    {
        $resolution = $agenda->resolution;
        $resolutionNo = trim((string) ($agenda->reso_ord_ao_no ?? ''));

        $resolution->update([
            'resolution_no' => $resolutionNo !== '' ? Str::limit($resolutionNo, 50, '') : $resolution->resolution_no,
            'resolution_title' => $agenda->resolution_title ?: $agenda->title,
            'series' => (int) ($agenda->reso_ord_ao_series ?: $resolution->series),
            'date_approved' => $agenda->date_passed ?? $agenda->date_signed_by_gov ?? $resolution->date_approved,
            'committee' => $agenda->committee_referred ?? $resolution->committee,
            'mun_title' => $agenda->title ?? $resolution->mun_title,
            'status' => ($agenda->date_passed ?? $agenda->date_signed_by_gov) ? 'approved' : $resolution->status,
            'sp_pdf_url' => $agenda->reso_ord_ao_url ?? $resolution->sp_pdf_url,
        ]);

        return true;
    }

    protected function syncOrdinance(AgendaItem $agenda): bool
    {
        $ordinance = $agenda->ordinance;

        $ordinance->update([
            'subject' => $agenda->resolution_title ?: $agenda->title,
            'date_enacted' => $agenda->date_passed ?? $ordinance->date_enacted,
            'date_approved' => $agenda->date_signed_by_gov ?? $ordinance->date_approved,
            'pdf_url' => $agenda->reso_ord_ao_url ?? $ordinance->pdf_url,
        ]);

        return true;
    }

    protected function syncAppropriationOrdinance(AgendaItem $agenda): bool
    {
        $appropriation = $agenda->appropriationOrdinance;

        $appropriation->update([
            'subject' => $agenda->resolution_title ?: $agenda->title,
            'date_passed' => $agenda->date_passed ?? $appropriation->date_passed,
            'date_approved' => $agenda->date_signed_by_gov ?? $appropriation->date_approved,
            'pdf_url' => $agenda->reso_ord_ao_url ?? $appropriation->pdf_url,
        ]);

        return true;
    }

    protected function publishResolution(AgendaItem $agenda, ?int $userId): bool
    {
        if ($agenda->resolution_id) {
            return false;
        }

        $series = (int) ($agenda->reso_ord_ao_series ?: now()->year);
        $resolutionNo = trim((string) ($agenda->reso_ord_ao_no ?? ''));

        $resolution = Resolution::create([
            'resolution_no' => $resolutionNo !== '' ? Str::limit($resolutionNo, 50, '') : '',
            'resolution_title' => $agenda->resolution_title ?: $agenda->title,
            'series' => $series,
            'date_approved' => $agenda->date_passed ?? $agenda->date_signed_by_gov,
            'date_received' => $agenda->date_received,
            'committee' => $agenda->committee_referred,
            'mun_title' => $agenda->title,
            'status' => ($agenda->date_passed ?? $agenda->date_signed_by_gov) ? 'approved' : 'draft',
            'document_type' => DocumentType::infer(
                $resolutionNo !== '' ? $resolutionNo : null,
                $agenda->resolution_title ?: $agenda->title,
            ),
            'sp_pdf_url' => $agenda->reso_ord_ao_url,
            'created_by' => $userId,
        ]);

        $agenda->forceFill([
            'resolution_id' => $resolution->id,
            'published_at' => now(),
        ])->save();

        return true;
    }

    protected function publishOrdinance(AgendaItem $agenda, ?int $userId): bool
    {
        if ($agenda->ordinance_id) {
            return false;
        }

        $series = (int) ($agenda->reso_ord_ao_series ?: now()->year);
        $ordinanceNo = OrdinanceNumberParser::parse($agenda->reso_ord_ao_no)
            ?? $this->extractNumericNo($agenda->reso_ord_ao_no);

        if ($ordinanceNo === null) {
            return false;
        }

        $ordinance = Ordinance::create([
            'ordinance_no' => $ordinanceNo,
            'series_year' => $series,
            'subject' => $agenda->resolution_title ?: $agenda->title,
            'date_enacted' => $agenda->date_passed,
            'date_approved' => $agenda->date_signed_by_gov,
            'pdf_url' => $agenda->reso_ord_ao_url,
        ]);

        app(OrdinanceVersionService::class)->recordInitialVersion($ordinance, $userId);

        $agenda->forceFill([
            'ordinance_id' => $ordinance->id,
            'published_at' => now(),
        ])->save();

        return true;
    }

    protected function publishAppropriationOrdinance(AgendaItem $agenda, ?int $userId): bool
    {
        if ($agenda->appropriation_ordinance_id) {
            return false;
        }

        $series = (int) ($agenda->reso_ord_ao_series ?: now()->year);
        $ordinanceNo = OrdinanceNumberParser::parse($agenda->reso_ord_ao_no)
            ?? $this->extractNumericNo($agenda->reso_ord_ao_no);

        if ($ordinanceNo === null) {
            return false;
        }

        $appropriation = AppropriationOrdinance::create([
            'date_received' => $agenda->date_received,
            'subject' => $agenda->resolution_title ?: $agenda->title,
            'ordinance_no' => $ordinanceNo,
            'series_year' => $series,
            'date_passed' => $agenda->date_passed,
            'date_approved' => $agenda->date_signed_by_gov,
            'pdf_url' => $agenda->reso_ord_ao_url,
            'agenda_item_id' => $agenda->id,
            'created_by' => $userId,
        ]);

        $agenda->forceFill([
            'appropriation_ordinance_id' => $appropriation->id,
            'published_at' => now(),
        ])->save();

        return true;
    }

    protected function extractNumericNo(?string $value): ?int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/(\d+)/', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
