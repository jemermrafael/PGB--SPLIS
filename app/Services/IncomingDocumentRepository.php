<?php

namespace App\Services;

use App\Models\IncomingDocument;
use App\Models\Municipality;
use App\Support\ResolutionNumberParser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class IncomingDocumentRepository
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        if (! empty($filters['has_pdf'])) {
            return $this->paginateWithPdfFilter($filters, $perPage);
        }

        return $this->baseQuery($filters)
            ->paginate($perPage)
            ->through(fn (IncomingDocument $item) => $this->toArray($item));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(IncomingDocument $item): array
    {
        $title = $item->sp_title ?: $item->title;
        $spNumber = $item->sp_res_no;
        if ($spNumber && $item->sp_series) {
            $spNumber = trim($spNumber).' / '.$item->sp_series;
        }

        return [
            'id' => $item->id,
            'legacy_file_id' => $item->legacy_file_id,
            'mun_resolution_no' => $item->mun_resolution_no,
            'municipality' => $item->municipality,
            'title' => $title,
            'sp_number' => $spNumber,
            'sp_series' => $item->sp_series,
            'committee' => $item->referral,
            'keyword' => $item->keyword,
            'date' => $item->sp_date_approved?->format('Y-m-d'),
            'workflow_status' => $item->workflow_status,
            'link_status' => $item->link_status,
            'is_linked' => $item->isLinked(),
            'source' => $item->source,
            'has_pdf' => $this->hasPdfUrl($item),
            'url' => route('incoming.show', $item),
        ];
    }

    protected function paginateWithPdfFilter(array $filters, int $perPage): LengthAwarePaginator
    {
        $items = $this->baseQuery($filters)
            ->get()
            ->filter(fn (IncomingDocument $item) => $this->hasPdfUrl($item))
            ->values();

        $page = max(1, (int) ($filters['page'] ?? request()->integer('page', 1)));
        $total = $items->count();
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values()
            ->map(fn (IncomingDocument $item) => $this->toArray($item))
            ->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    protected function baseQuery(array $filters): Builder
    {
        $query = IncomingDocument::query()->with('resolution');

        if (! empty($filters['number'])) {
            $number = trim((string) $filters['number']);
            $query->where(function (Builder $q) use ($number) {
                $like = '%'.$number.'%';
                $q->where('sp_res_no', 'like', $like)
                    ->orWhere('mun_resolution_no', 'like', $like);

                if (preg_match('/^(\d{4})-(\d+)$/', $number, $m)) {
                    $year = (int) $m[1];
                    $sequence = (int) $m[2];
                    $q->orWhere('sp_series', $year)
                        ->where(function (Builder $inner) use ($sequence) {
                            $inner->where('sp_res_no', 'like', '%'.$sequence.'%')
                                ->orWhere('sp_res_no', ResolutionNumberParser::buildOfficialNumber($year, $sequence));
                        });
                }
            });
        }

        if (! empty($filters['title'])) {
            $like = '%'.$filters['title'].'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('sp_title', 'like', $like)
                    ->orWhere('title', 'like', $like);
            });
        }

        if (! empty($filters['committee'])) {
            $query->where('referral', 'like', '%'.$filters['committee'].'%');
        }

        if (! empty($filters['keyword'])) {
            $query->where('keyword', 'like', '%'.$filters['keyword'].'%');
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('sp_date_approved', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('sp_date_approved', '<=', $filters['date_to']);
        }

        if (! empty($filters['series'])) {
            $query->where('sp_series', (int) $filters['series']);
        }

        if (! empty($filters['status'])) {
            $query->where('workflow_status', 'like', '%'.$filters['status'].'%');
        }

        if (! empty($filters['link_status'])) {
            $query->where('link_status', $filters['link_status']);
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['municipality_id'])) {
            $label = Municipality::query()->whereKey($filters['municipality_id'])->value('description');
            if ($label) {
                $query->where('municipality', 'like', '%'.$label.'%');
            }
        }

        if (! empty($filters['category_id'])) {
            $query->whereHas('resolution', fn (Builder $q) => $q->where('category_id', $filters['category_id']));
        }

        if (! empty($filters['department_id'])) {
            $query->whereHas('resolution', fn (Builder $q) => $q->where('department_id', $filters['department_id']));
        }

        return $query->latest('id');
    }

    protected function hasPdfUrl(IncomingDocument $item): bool
    {
        return filled($item->sp_pdf_url) || filled($item->mun_pdf_url);
    }
}
