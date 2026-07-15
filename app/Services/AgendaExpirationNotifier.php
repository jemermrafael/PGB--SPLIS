<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Support\AgendaDeadline;
use Illuminate\Database\Eloquent\Builder;

class AgendaExpirationNotifier
{
    public function __construct(
        private BoardMemberNotifier $boardMemberNotifier,
        private MunicipalNotifier $municipalNotifier,
    ) {}

    public function isExpiringSoon(AgendaItem $agenda): bool
    {
        return AgendaDeadline::isWithinExpiringSoonWindow($agenda->due_date, $agenda->status);
    }

    public function syncForAgenda(AgendaItem $agenda): void
    {
        if (! $this->isExpiringSoon($agenda)) {
            return;
        }

        $this->boardMemberNotifier->notifyAgendaExpiringSoon($agenda);
        $this->municipalNotifier->notifyAgendaExpiringSoon($agenda);
    }

    public function syncAll(): int
    {
        $count = 0;

        $this->expiringSoonQuery()->chunkById(100, function ($agendas) use (&$count): void {
            foreach ($agendas as $agenda) {
                $this->boardMemberNotifier->notifyAgendaExpiringSoon($agenda);
                $this->municipalNotifier->notifyAgendaExpiringSoon($agenda);
                $count++;
            }
        });

        return $count;
    }

    /** @return Builder<AgendaItem> */
    public function expiringSoonQuery(): Builder
    {
        return AgendaItem::query()->expiringSoon();
    }
}
