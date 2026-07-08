<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\AppropriationOrdinance;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Support\AgendaMeasureType;
use App\Support\OrdinanceNumberParser;

class AgendaOutputLinker
{
    public function linkExistingIfPossible(AgendaItem $agenda): bool
    {
        if ($agenda->isPublished()) {
            return false;
        }

        $measureType = $agenda->reso_ord_ao_type
            ?? AgendaItem::inferMeasureType($agenda->resolution_title ?? $agenda->title);

        if ($measureType === null || ! filled($agenda->reso_ord_ao_no) || ! $agenda->reso_ord_ao_series) {
            return false;
        }

        return match ($measureType) {
            AgendaMeasureType::RESOLUTION => $this->linkResolution($agenda),
            AgendaMeasureType::ORDINANCE => $this->linkOrdinance($agenda),
            AgendaMeasureType::APPROPRIATION_ORDINANCE => $this->linkAppropriationOrdinance($agenda),
            default => false,
        };
    }

    public function findResolution(AgendaItem $agenda): ?Resolution
    {
        $series = (int) $agenda->reso_ord_ao_series;
        $number = trim((string) $agenda->reso_ord_ao_no);

        if ($number === '' || $series <= 0) {
            return null;
        }

        $candidates = array_values(array_unique(array_filter([
            $number,
            ltrim($number, '0') !== '' ? ltrim($number, '0') : null,
            ctype_digit($number) ? str_pad($number, 3, '0', STR_PAD_LEFT) : null,
        ])));

        return Resolution::query()
            ->where('series', $series)
            ->where(function ($query) use ($candidates): void {
                foreach ($candidates as $candidate) {
                    $query->orWhere('resolution_no', $candidate);
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    public function findOrdinance(AgendaItem $agenda): ?Ordinance
    {
        $series = (int) $agenda->reso_ord_ao_series;
        $ordinanceNo = OrdinanceNumberParser::parse($agenda->reso_ord_ao_no)
            ?? $this->extractNumericNo($agenda->reso_ord_ao_no);

        if ($ordinanceNo === null || $series <= 0) {
            return null;
        }

        return Ordinance::query()
            ->where('ordinance_no', $ordinanceNo)
            ->where('series_year', $series)
            ->orderByDesc('id')
            ->first();
    }

    public function findAppropriationOrdinance(AgendaItem $agenda): ?AppropriationOrdinance
    {
        $series = (int) $agenda->reso_ord_ao_series;
        $ordinanceNo = OrdinanceNumberParser::parse($agenda->reso_ord_ao_no)
            ?? $this->extractNumericNo($agenda->reso_ord_ao_no);

        if ($ordinanceNo === null || $series <= 0) {
            return null;
        }

        return AppropriationOrdinance::query()
            ->where('ordinance_no', $ordinanceNo)
            ->where('series_year', $series)
            ->orderByDesc('id')
            ->first();
    }

    protected function linkResolution(AgendaItem $agenda): bool
    {
        $resolution = $this->findResolution($agenda);

        if (! $resolution) {
            return false;
        }

        $agenda->forceFill([
            'resolution_id' => $resolution->id,
            'ordinance_id' => null,
            'appropriation_ordinance_id' => null,
            'reso_ord_ao_type' => AgendaMeasureType::RESOLUTION,
            'published_at' => $agenda->published_at ?? now(),
        ])->save();

        return true;
    }

    protected function linkOrdinance(AgendaItem $agenda): bool
    {
        $ordinance = $this->findOrdinance($agenda);

        if (! $ordinance) {
            return false;
        }

        $agenda->forceFill([
            'ordinance_id' => $ordinance->id,
            'resolution_id' => null,
            'appropriation_ordinance_id' => null,
            'reso_ord_ao_type' => AgendaMeasureType::ORDINANCE,
            'published_at' => $agenda->published_at ?? now(),
        ])->save();

        return true;
    }

    protected function linkAppropriationOrdinance(AgendaItem $agenda): bool
    {
        $appropriation = $this->findAppropriationOrdinance($agenda);

        if (! $appropriation) {
            return false;
        }

        $agenda->forceFill([
            'appropriation_ordinance_id' => $appropriation->id,
            'resolution_id' => null,
            'ordinance_id' => null,
            'reso_ord_ao_type' => AgendaMeasureType::APPROPRIATION_ORDINANCE,
            'published_at' => $agenda->published_at ?? now(),
        ])->save();

        if ($appropriation->agenda_item_id !== $agenda->id) {
            $appropriation->update(['agenda_item_id' => $agenda->id]);
        }

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
