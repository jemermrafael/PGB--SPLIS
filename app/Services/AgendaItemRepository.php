<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Support\AgendaDeadline;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AgendaItemRepository
{
    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        $today = now()->startOfDay();
        $dueSoonEnd = now()->addDays(7)->endOfDay();

        return [
            'total' => AgendaItem::query()->count(),
            'pending' => AgendaItem::query()->where('status', AgendaItem::STATUS_PENDING)->count(),
            'due_soon' => AgendaItem::query()
                ->where('status', AgendaItem::STATUS_PENDING)
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$today, $dueSoonEnd])
                ->count(),
            'done' => AgendaItem::query()->where('status', AgendaItem::STATUS_DONE)->count(),
            'lapsed' => AgendaItem::query()->where('status', AgendaItem::STATUS_LAPSED)->count(),
            'no_due_date' => AgendaItem::query()->where('status', AgendaItem::STATUS_NO_DUE_DATE)->count(),
            'has_incoming' => AgendaItem::query()->whereNotNull('incoming_document_id')->count(),
        ];
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->paginateFromBuilder(AgendaItem::query(), $filters, $perPage);
    }

    public function paginateFromBuilder(Builder $query, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->applyFilters($query, $filters)
            ->latest('date_received')
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (AgendaItem $item) => $this->toArray($item));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(AgendaItem $item): array
    {
        return [
            'id' => $item->id,
            'tracking_no' => $item->tracking_no,
            'date_received' => $item->date_received?->format('Y-m-d'),
            'sender' => $item->sender,
            'title' => $item->title,
            'committee' => $item->committee_referred,
            'due_date' => $item->due_date?->format('Y-m-d'),
            'days_left_label' => $item->days_left_label,
            'days_left_tone' => AgendaDeadline::toneForItem($item),
            'status' => $item->status,
            'status_label' => config('agenda.statuses.'.$item->status, $item->status),
            'outcome' => $item->outcome,
            'reso_label' => $item->resoDisplayLabel(),
            'has_incoming' => $item->hasIncoming(),
            'has_resolution' => $item->resolution_id !== null,
            'published_to' => $item->publishedTargetLabel(),
            'has_pdf' => $item->hasAnyPdf(),
            'date_of_referral' => $item->date_of_referral?->format('Y-m-d'),
            'url' => route('agenda.show', $item),
        ];
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query = $query->with(['incomingDocument', 'resolution', 'ordinance', 'appropriationOrdinance']);

        if (! empty($filters['title'])) {
            $query->where('title', 'like', '%'.$filters['title'].'%');
        }

        if (! empty($filters['sender'])) {
            $query->where('sender', 'like', '%'.$filters['sender'].'%');
        }

        if (! empty($filters['committee'])) {
            $query->where('committee_referred', 'like', '%'.$filters['committee'].'%');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['outcome'])) {
            $query->where('outcome', 'like', '%'.$filters['outcome'].'%');
        }

        if (! empty($filters['number'])) {
            $term = trim($filters['number']);
            $query->where(function (Builder $q) use ($term) {
                $q->where('tracking_no', 'like', '%'.$term.'%')
                    ->orWhere('reso_ord_ao_no', 'like', '%'.$term.'%');
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('date_received', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('date_received', '<=', $filters['date_to']);
        }

        if (! empty($filters['series'])) {
            $query->where('reso_ord_ao_series', (int) $filters['series']);
        }

        if (! empty($filters['due_soon'])) {
            $today = now()->startOfDay();
            $dueSoonEnd = now()->addDays(7)->endOfDay();
            $query->where('status', AgendaItem::STATUS_PENDING)
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$today, $dueSoonEnd]);
        }

        if (! empty($filters['has_incoming'])) {
            $query->whereNotNull('incoming_document_id');
        }

        return $query;
    }
}
