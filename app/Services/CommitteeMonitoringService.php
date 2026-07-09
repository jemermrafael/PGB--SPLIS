<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\Committee;
use App\Support\CommitteeLookup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CommitteeMonitoringService
{
    /**
     * @return Collection<int, Committee>
     */
    public function committeeOptions(int $termId): Collection
    {
        return Committee::query()
            ->active()
            ->ordered()
            ->withRosterForTerm($termId)
            ->get(['id', 'name']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function stats(array $filters): array
    {
        $query = $this->baseQuery($filters);

        $total = (clone $query)->count();
        $withSchedule = (clone $query)->whereNotNull('date_of_committee_meeting')->count();
        $withReport = (clone $query)->whereNotNull('committee_report_url')->where('committee_report_url', '!=', '')->count();
        $completed = (clone $query)->whereNotNull('outcome')->where('outcome', '!=', '')->count();

        return [
            'total' => $total,
            'with_schedule' => $withSchedule,
            'with_report' => $withReport,
            'completed' => $completed,
            'pending' => max($total - $completed, 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function baseQuery(array $filters): Builder
    {
        $query = AgendaItem::query()
            ->whereNotNull('committee_referred')
            ->where('committee_referred', '!=', '');

        if (! empty($filters['committee_id'])) {
            $query->where(function (Builder $builder) use ($filters): void {
                /** @var Committee|null $committee */
                $committee = Committee::query()->find((int) $filters['committee_id']);
                if (! $committee) {
                    $builder->whereRaw('1 = 0');

                    return;
                }

                $name = trim((string) $committee->name);
                $normalized = CommitteeLookup::normalizeReferralName($name);
                $short = trim((string) preg_replace('/,.*/', '', $normalized));

                $builder->where('committee_referred', 'like', '%'.$name.'%')
                    ->orWhere('committee_referred', 'like', '%'.$normalized.'%');

                if ($short !== '' && $short !== $normalized) {
                    $builder->orWhere('committee_referred', 'like', '%'.$short.'%');
                }
            });
        }

        if (($filters['status'] ?? '') === 'pending') {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('outcome')->orWhere('outcome', '');
            });
        } elseif (($filters['status'] ?? '') === 'completed') {
            $query->whereNotNull('outcome')->where('outcome', '!=', '');
        }

        if (($filters['has_report'] ?? '') === 'yes') {
            $query->whereNotNull('committee_report_url')->where('committee_report_url', '!=', '');
        } elseif (($filters['has_report'] ?? '') === 'no') {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('committee_report_url')->orWhere('committee_report_url', '');
            });
        }

        if (($filters['has_schedule'] ?? '') === 'yes') {
            $query->whereNotNull('date_of_committee_meeting');
        } elseif (($filters['has_schedule'] ?? '') === 'no') {
            $query->whereNull('date_of_committee_meeting');
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('date_of_referral', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('date_of_referral', '<=', (string) $filters['date_to']);
        }

        if (! empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('title', 'like', '%'.$term.'%')
                    ->orWhere('tracking_no', 'like', '%'.$term.'%')
                    ->orWhere('sender', 'like', '%'.$term.'%')
                    ->orWhere('outcome', 'like', '%'.$term.'%');
            });
        }

        return $query->orderByDesc('date_of_referral')->orderByDesc('date_received')->orderByDesc('id');
    }
}

