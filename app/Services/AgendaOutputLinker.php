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

        if (! filled($agenda->reso_ord_ao_no) || ! $agenda->reso_ord_ao_series) {
            return false;
        }

        $measureType = $agenda->reso_ord_ao_type;

        if ($measureType === null) {
            if ($this->linkResolution($agenda)) {
                return true;
            }
            if ($this->linkOrdinance($agenda)) {
                return true;
            }

            return $this->linkAppropriationOrdinance($agenda);
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
        $number = $this->extractResolutionNoForSeries($agenda->reso_ord_ao_no, $series);
        $numberInt = $this->normalizeResolutionNoToInt($number, $series);

        if ($number === '' || $series <= 0 || $numberInt === null) {
            return null;
        }

        $exact = Resolution::query()
            ->where('series', $series)
            ->where('resolution_no', $number)
            ->orderByDesc('id')
            ->first();

        if ($exact && $this->normalizeResolutionNoToInt($exact->resolution_no, $series) === $numberInt) {
            return $exact;
        }

        $fuzzyCandidates = Resolution::query()
            ->where('series', $series)
            ->where('resolution_no', 'like', '%'.$number.'%')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return $fuzzyCandidates
            ->first(fn (Resolution $resolution) => $this->normalizeResolutionNoToInt($resolution->resolution_no, $series) === $numberInt);
    }

    protected function extractResolutionNoForSeries(?string $value, int $series): string
    {
        $value = trim((string) $value);

        if ($value === '' || $series <= 0) {
            return '';
        }

        if (preg_match('/(?:^|[^0-9])(\d{4})\s*[-\/]\s*(\d+)(?:[^0-9]|$)/', $value, $matches)) {
            if ((int) $matches[1] === $series) {
                return ltrim($matches[2], '0') ?: '0';
            }
        }

        if (preg_match('/(\d+)\s*[-\/]\s*(\d{4})/', $value, $matches)) {
            if ((int) $matches[2] === $series) {
                return ltrim($matches[1], '0') ?: '0';
            }
        }

        if (preg_match('/(\d+)/', $value, $matches)) {
            return ltrim($matches[1], '0') ?: '0';
        }

        return '';
    }

    protected function normalizeResolutionNoToInt(?string $value, int $series): ?int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/(?:^|[^0-9])(\d{4})\s*[-\/]\s*(\d+)(?:[^0-9]|$)/', $value, $matches)) {
            if ((int) $matches[1] === $series) {
                return (int) $matches[2];
            }
        }

        if (preg_match('/(\d+)\s*[-\/]\s*(\d{4})/', $value, $matches)) {
            if ((int) $matches[2] === $series) {
                return (int) $matches[1];
            }
        }

        if (preg_match('/(\d+)/', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
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
