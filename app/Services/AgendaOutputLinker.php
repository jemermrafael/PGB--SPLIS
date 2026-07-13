<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\AppropriationOrdinance;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Support\AgendaMeasureType;
use App\Support\OrdinanceNumberParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AgendaOutputLinker
{
    public function clearDanglingOutputLinks(AgendaItem $agenda): bool
    {
        $updates = [];

        if ($agenda->resolution_id && ! $agenda->resolution()->exists()) {
            $updates['resolution_id'] = null;
        }

        if ($agenda->ordinance_id && ! $agenda->ordinance()->exists()) {
            $updates['ordinance_id'] = null;
        }

        if ($agenda->appropriation_ordinance_id && ! $agenda->appropriationOrdinance()->exists()) {
            $updates['appropriation_ordinance_id'] = null;
        }

        if ($updates === []) {
            return false;
        }

        $agenda->forceFill($updates);

        if (! $agenda->resolution_id && ! $agenda->ordinance_id && ! $agenda->appropriation_ordinance_id) {
            $agenda->published_at = null;
        }

        $agenda->save();

        return true;
    }

    public function linkExistingIfPossible(AgendaItem $agenda): bool
    {
        $this->clearDanglingOutputLinks($agenda);
        $agenda->refresh();

        if ($agenda->isPublished()) {
            return false;
        }

        if (! filled($agenda->reso_ord_ao_no) || ! $agenda->reso_ord_ao_series) {
            return false;
        }

        $measureType = $agenda->effectiveMeasureType();

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

    /**
     * @return Collection<int, array{id: int, label: string, type: string}>
     */
    public function candidateOptions(AgendaItem $agenda): Collection
    {
        $measureType = $agenda->effectiveMeasureType();
        $series = (int) $agenda->reso_ord_ao_series;

        if ($series < 1) {
            return collect();
        }

        return match ($measureType) {
            AgendaMeasureType::RESOLUTION => $this->candidateResolutions($agenda)
                ->map(fn (Resolution $r) => [
                    'id' => $r->id,
                    'type' => AgendaMeasureType::RESOLUTION,
                    'label' => trim($r->resolution_no.' / '.$r->series.' — '.Str::limit($r->resolution_title ?? '', 80)),
                ]),
            AgendaMeasureType::ORDINANCE => $this->candidateOrdinances($agenda)
                ->map(fn (Ordinance $o) => [
                    'id' => $o->id,
                    'type' => AgendaMeasureType::ORDINANCE,
                    'label' => $o->displayNumber().' ('.$o->series_year.') — '.Str::limit($o->subject ?? '', 80),
                ]),
            AgendaMeasureType::APPROPRIATION_ORDINANCE => $this->candidateAppropriationOrdinances($agenda)
                ->map(fn (AppropriationOrdinance $o) => [
                    'id' => $o->id,
                    'type' => AgendaMeasureType::APPROPRIATION_ORDINANCE,
                    'label' => $o->displayNumber().' ('.$o->series_year.') — '.Str::limit($o->subject ?? '', 80),
                ]),
            default => collect(),
        };
    }

    public function linkManual(AgendaItem $agenda, string $type, int $id): bool
    {
        $this->clearDanglingOutputLinks($agenda);
        $agenda->refresh();

        return match ($type) {
            AgendaMeasureType::RESOLUTION => $this->attachResolution($agenda, Resolution::query()->findOrFail($id)),
            AgendaMeasureType::ORDINANCE => $this->attachOrdinance($agenda, Ordinance::query()->findOrFail($id)),
            AgendaMeasureType::APPROPRIATION_ORDINANCE => $this->attachAppropriationOrdinance(
                $agenda,
                AppropriationOrdinance::query()->findOrFail($id),
            ),
            default => false,
        };
    }

    public function findResolution(AgendaItem $agenda): ?Resolution
    {
        $series = (int) $agenda->reso_ord_ao_series;
        $numberInt = $this->normalizeResolutionNoToInt($agenda->reso_ord_ao_no, $series);

        if ($numberInt === null || $series <= 0) {
            return null;
        }

        $variants = $this->resolutionNumberVariants($numberInt, $series);

        $exact = Resolution::query()
            ->where('series', $series)
            ->whereIn('resolution_no', $variants)
            ->orderByDesc('id')
            ->first();

        if ($exact) {
            return $exact;
        }

        return Resolution::query()
            ->where('series', $series)
            ->where(function ($query) use ($series, $numberInt) {
                $padded = str_pad((string) $numberInt, 3, '0', STR_PAD_LEFT);
                $query->where('resolution_no', 'like', $series.'-'.$numberInt)
                    ->orWhere('resolution_no', 'like', $series.'-'.$padded)
                    ->orWhere('resolution_no', 'like', $series.'-/'.$numberInt)
                    ->orWhere('resolution_no', (string) $numberInt)
                    ->orWhere('resolution_no', $padded);
            })
            ->orderByDesc('id')
            ->get()
            ->first(fn (Resolution $resolution) => $this->normalizeResolutionNoToInt($resolution->resolution_no, $series) === $numberInt);
    }

    /**
     * @return Collection<int, Resolution>
     */
    public function candidateResolutions(AgendaItem $agenda): Collection
    {
        $series = (int) $agenda->reso_ord_ao_series;
        $numberInt = $this->normalizeResolutionNoToInt($agenda->reso_ord_ao_no, $series);

        if ($series <= 0) {
            return collect();
        }

        $query = Resolution::query()
            ->where('series', $series)
            ->orderByDesc('id')
            ->limit(75);

        if ($numberInt !== null) {
            $padded = str_pad((string) $numberInt, 3, '0', STR_PAD_LEFT);
            $query->where(function ($builder) use ($series, $numberInt, $padded) {
                $builder->whereIn('resolution_no', $this->resolutionNumberVariants($numberInt, $series))
                    ->orWhere('resolution_no', 'like', '%-'.$padded)
                    ->orWhere('resolution_no', 'like', '%-'.$numberInt)
                    ->orWhere('resolution_no', 'like', $padded.'%')
                    ->orWhere('resolution_no', 'like', (string) $numberInt.'%');
            });
        }

        return $query->get();
    }

    /**
     * @return Collection<int, Ordinance>
     */
    public function candidateOrdinances(AgendaItem $agenda): Collection
    {
        $series = (int) $agenda->reso_ord_ao_series;
        $ordinanceNo = OrdinanceNumberParser::parse($agenda->reso_ord_ao_no)
            ?? $this->extractNumericNo($agenda->reso_ord_ao_no);

        if ($series <= 0) {
            return collect();
        }

        $query = Ordinance::query()
            ->where('series_year', $series)
            ->orderByDesc('id')
            ->limit(75);

        if ($ordinanceNo !== null) {
            $query->where(function ($builder) use ($ordinanceNo) {
                $builder->where('ordinance_no', $ordinanceNo)
                    ->orWhere('ordinance_no', 'like', $ordinanceNo.'%');
            });
        }

        return $query->get();
    }

    /**
     * @return Collection<int, AppropriationOrdinance>
     */
    public function candidateAppropriationOrdinances(AgendaItem $agenda): Collection
    {
        $series = (int) $agenda->reso_ord_ao_series;
        $ordinanceNo = OrdinanceNumberParser::parse($agenda->reso_ord_ao_no)
            ?? $this->extractNumericNo($agenda->reso_ord_ao_no);

        if ($series <= 0) {
            return collect();
        }

        $query = AppropriationOrdinance::query()
            ->where('series_year', $series)
            ->orderByDesc('id')
            ->limit(75);

        if ($ordinanceNo !== null) {
            $query->where(function ($builder) use ($ordinanceNo) {
                $builder->where('ordinance_no', $ordinanceNo)
                    ->orWhere('ordinance_no', 'like', $ordinanceNo.'%');
            });
        }

        return $query->get();
    }

    /**
     * @return list<string>
     */
    protected function resolutionNumberVariants(int $numberInt, int $series): array
    {
        $bare = (string) $numberInt;
        $pad3 = str_pad($bare, 3, '0', STR_PAD_LEFT);
        $pad4 = str_pad($bare, 4, '0', STR_PAD_LEFT);

        return array_values(array_unique([
            $bare,
            $pad3,
            $pad4,
            $series.'-'.$bare,
            $series.'-'.$pad3,
            $series.'-'.$pad4,
            $series.'/'.$bare,
            $series.'/'.$pad3,
        ]));
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

        return $resolution ? $this->attachResolution($agenda, $resolution) : false;
    }

    protected function linkOrdinance(AgendaItem $agenda): bool
    {
        $ordinance = $this->findOrdinance($agenda);

        return $ordinance ? $this->attachOrdinance($agenda, $ordinance) : false;
    }

    protected function linkAppropriationOrdinance(AgendaItem $agenda): bool
    {
        $appropriation = $this->findAppropriationOrdinance($agenda);

        return $appropriation ? $this->attachAppropriationOrdinance($agenda, $appropriation) : false;
    }

    protected function attachResolution(AgendaItem $agenda, Resolution $resolution): bool
    {
        $agenda->forceFill([
            'resolution_id' => $resolution->id,
            'ordinance_id' => null,
            'appropriation_ordinance_id' => null,
            'reso_ord_ao_type' => AgendaMeasureType::RESOLUTION,
            'published_at' => $agenda->published_at ?? now(),
        ])->save();

        return true;
    }

    protected function attachOrdinance(AgendaItem $agenda, Ordinance $ordinance): bool
    {
        $agenda->forceFill([
            'ordinance_id' => $ordinance->id,
            'resolution_id' => null,
            'appropriation_ordinance_id' => null,
            'reso_ord_ao_type' => AgendaMeasureType::ORDINANCE,
            'published_at' => $agenda->published_at ?? now(),
        ])->save();

        return true;
    }

    protected function attachAppropriationOrdinance(AgendaItem $agenda, AppropriationOrdinance $appropriation): bool
    {
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
