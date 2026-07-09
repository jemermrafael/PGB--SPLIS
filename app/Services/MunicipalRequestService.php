<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\Municipality;
use App\Models\User;
use App\Support\AgendaDeadline;
use App\Support\MunicipalRequestAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MunicipalRequestService
{
    public function municipalityFor(User $user): ?Municipality
    {
        if (! $user->isMunicipalViewer()) {
            return null;
        }

        return $user->municipality;
    }

    /**
     * @return Builder<AgendaItem>
     */
    public function requestQueryFor(User $user): Builder
    {
        $municipality = $this->municipalityFor($user);

        if ($municipality === null) {
            return AgendaItem::query()->whereRaw('0 = 1');
        }

        return $this->requestQueryForMunicipality($municipality);
    }

    /**
     * @return Builder<AgendaItem>
     */
    public function requestQueryForMunicipality(Municipality $municipality): Builder
    {
        return AgendaItem::query()
            ->whereRaw('LOWER(TRIM(sender)) = ?', [mb_strtolower($municipality->senderLabel())]);
    }

    /**
     * @return array<string, int>
     */
    public function statsFor(User $user): array
    {
        $base = $this->requestQueryFor($user);

        return [
            'pending' => (clone $base)->where('status', AgendaItem::STATUS_PENDING)->count(),
            'expiring_soon' => (clone $base)->expiringSoon()->count(),
            'due_soon' => (clone $base)->dueSoon()->count(),
            'done' => (clone $base)->where('status', AgendaItem::STATUS_DONE)->count(),
            'lapsed' => (clone $base)->where('status', AgendaItem::STATUS_LAPSED)->count(),
        ];
    }

    /**
     * @return Collection<int, AgendaItem>
     */
    public function expiringSoonRequestsFor(User $user, int $limit = 8): Collection
    {
        return $this->requestQueryFor($user)
            ->expiringSoon()
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function expiringSoonDays(): int
    {
        return AgendaDeadline::expiringSoonDays();
    }

    public function userCanViewAgenda(User $user, AgendaItem $agenda): bool
    {
        return MunicipalRequestAccess::userCanViewAgenda($user, $agenda);
    }
}
