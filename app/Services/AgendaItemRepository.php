<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Support\AgendaDeadline;
use App\Support\CommitteeIcon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AgendaItemRepository
{
    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        return [
            'total' => AgendaItem::query()->notArchived()->count(),
            'pending' => AgendaItem::query()->notArchived()->where('status', AgendaItem::STATUS_PENDING)->count(),
            'expiring_soon' => AgendaItem::query()->notArchived()->expiringSoon()->count(),
            'due_soon' => AgendaItem::query()->notArchived()->dueSoon()->count(),
            'done' => AgendaItem::query()->notArchived()->where('status', AgendaItem::STATUS_DONE)->count(),
            'lapsed' => AgendaItem::query()->notArchived()->where('status', AgendaItem::STATUS_LAPSED)->count(),
            'no_due_date' => AgendaItem::query()->notArchived()->where('status', AgendaItem::STATUS_NO_DUE_DATE)->count(),
            'has_incoming' => AgendaItem::query()->notArchived()->whereNotNull('incoming_document_id')->count(),
        ];
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->paginateFromBuilder(AgendaItem::query()->notArchived(), $filters, $perPage);
    }

    public function paginateArchived(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->paginateFromBuilder(
            AgendaItem::query()->archived()->with('archiver'),
            $filters,
            $perPage,
        );
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
            'display_label' => $item->displayLabel(),
            'list_number' => $item->listNumberLabel(),
            'list_year' => $item->listYearLabel(),
            'is_urgent_request' => $item->is_urgent_request,
            'date_received' => $item->date_received?->format('Y-m-d'),
            'sender' => $item->sender,
            'title' => $item->title,
            'committee' => $item->committee_referred,
            ...CommitteeIcon::listIconFields($item->committee_referred),
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
            'remarks' => $item->remarks,
            'date_of_referral' => $item->date_of_referral?->format('Y-m-d'),
            'archived_at' => $item->archived_at?->format('Y-m-d H:i'),
            'archived_by' => $item->archiver?->name,
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
            $query->dueSoon();
        }

        if (! empty($filters['expiring_soon'])) {
            $query->expiringSoon();
        }

        if (! empty($filters['has_incoming'])) {
            $query->whereNotNull('incoming_document_id');
        }

        if (! empty($filters['has_remarks'])) {
            $query->whereNotNull('remarks')->where('remarks', '!=', '');
        }

        if (! empty($filters['output_connection'])) {
            $this->applyOutputConnectionFilter($query, (string) $filters['output_connection']);
        }

        return $query;
    }

    protected function applyOutputConnectionFilter(Builder $query, string $filter): void
    {
        $hasAnyOutput = function (Builder $builder): void {
            $builder->whereNotNull('resolution_id')
                ->orWhereNotNull('ordinance_id')
                ->orWhereNotNull('appropriation_ordinance_id');
        };

        match ($filter) {
            'any' => $query->where($hasAnyOutput),
            'none' => $query->whereNull('resolution_id')
                ->whereNull('ordinance_id')
                ->whereNull('appropriation_ordinance_id'),
            'linked' => $query->where('output_connection_type', AgendaItem::OUTPUT_CONNECTION_LINKED)
                ->where($hasAnyOutput),
            'published' => $query->where('output_connection_type', AgendaItem::OUTPUT_CONNECTION_PUBLISHED)
                ->where($hasAnyOutput),
            'linked_resolution' => $query->where('output_connection_type', AgendaItem::OUTPUT_CONNECTION_LINKED)
                ->whereNotNull('resolution_id'),
            'published_resolution' => $query->where('output_connection_type', AgendaItem::OUTPUT_CONNECTION_PUBLISHED)
                ->whereNotNull('resolution_id'),
            'linked_ordinance' => $query->where('output_connection_type', AgendaItem::OUTPUT_CONNECTION_LINKED)
                ->whereNotNull('ordinance_id'),
            'published_ordinance' => $query->where('output_connection_type', AgendaItem::OUTPUT_CONNECTION_PUBLISHED)
                ->whereNotNull('ordinance_id'),
            'linked_appropriation_ordinance' => $query->where('output_connection_type', AgendaItem::OUTPUT_CONNECTION_LINKED)
                ->whereNotNull('appropriation_ordinance_id'),
            'published_appropriation_ordinance' => $query->where('output_connection_type', AgendaItem::OUTPUT_CONNECTION_PUBLISHED)
                ->whereNotNull('appropriation_ordinance_id'),
            default => null,
        };
    }
}
