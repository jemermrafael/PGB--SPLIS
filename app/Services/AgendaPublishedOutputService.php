<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\AppropriationOrdinance;
use App\Models\Ordinance;
use App\Models\Resolution;

class AgendaPublishedOutputService
{
    /**
     * @var list<string>
     */
    public const OUTPUT_DETAIL_FIELDS = [
        'reso_ord_ao_no',
        'reso_ord_ao_series',
        'reso_ord_ao_type',
        'reso_ord_ao_url',
        'resolution_title',
        'date_passed',
        'date_signed_by_gov',
    ];

    public function clearFromDeletedResolution(Resolution $resolution): void
    {
        AgendaItem::query()
            ->where('resolution_id', $resolution->id)
            ->each(fn (AgendaItem $agenda) => $this->clearPublishedOutput($agenda));
    }

    public function clearFromDeletedOrdinance(Ordinance $ordinance): void
    {
        AgendaItem::query()
            ->where('ordinance_id', $ordinance->id)
            ->each(fn (AgendaItem $agenda) => $this->clearPublishedOutput($agenda));
    }

    public function clearFromDeletedAppropriationOrdinance(AppropriationOrdinance $appropriationOrdinance): void
    {
        AgendaItem::query()
            ->where('appropriation_ordinance_id', $appropriationOrdinance->id)
            ->each(fn (AgendaItem $agenda) => $this->clearPublishedOutput($agenda));

        AgendaItem::query()
            ->where('id', $appropriationOrdinance->agenda_item_id)
            ->each(fn (AgendaItem $agenda) => $this->clearPublishedOutput($agenda));
    }

    public function clearPublishedOutput(AgendaItem $agenda): void
    {
        $clears = array_fill_keys(self::OUTPUT_DETAIL_FIELDS, null);

        $agenda->forceFill(array_merge($clears, [
            'resolution_id' => null,
            'ordinance_id' => null,
            'appropriation_ordinance_id' => null,
            'published_at' => null,
        ]))->saveQuietly();
    }
}
