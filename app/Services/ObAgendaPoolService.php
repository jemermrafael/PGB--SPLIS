<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\ObBlock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ObAgendaPoolService
{
    /**
     * @return LengthAwarePaginator<int, AgendaItem>
     */
    public function paginate(
        ?string $search = null,
        int $page = 1,
        int $perPage = 20,
        ?int $excludeDocumentId = null,
    ): LengthAwarePaginator {
        $query = AgendaItem::query()->orderByDesc('date_received')->orderByDesc('id');
        if ($search !== null && $search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('tracking_no', 'like', '%'.$search.'%')
                    ->orWhere('sender', 'like', '%'.$search.'%');
            });
        }

        if ($excludeDocumentId !== null) {
            $query->whereNotIn('id', ObBlock::query()
                ->where('ob_document_id', $excludeDocumentId)
                ->whereNotNull('agenda_item_id')
                ->select('agenda_item_id'));
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeItems(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->items())
            ->map(fn (AgendaItem $item) => [
                'id' => $item->id,
                'tracking_no' => $item->tracking_no,
                'label' => $item->displayLabel(),
                'title' => $item->title,
                'sender' => $item->sender,
                'date_received' => $item->date_received?->format('Y-m-d'),
                'date_received_display' => $item->date_received?->format('M d, Y'),
                'due_date_display' => $item->due_date?->format('M d, Y'),
                'committee_referred' => $item->committee_referred,
                'status' => $item->status,
            ])
            ->values()
            ->all();
    }
}
