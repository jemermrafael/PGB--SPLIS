<?php

namespace App\Services;

use App\Data\ResolutionItem;
use App\Models\Ordinance;
use App\Support\DocumentType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class DashboardDocumentSearchService
{
    /**
     * @var list<string>
     */
    private const RESOLUTION_ONLY_FILTERS = [
        'author',
        'committee',
        'status',
        'category_id',
        'department_id',
        'municipality_id',
    ];

    public function __construct(
        protected ResolutionRepository $resolutions,
        protected OrdinanceSearchService $ordinances,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $documentType = (string) ($filters['document_type'] ?? '');

        if ($documentType === DocumentType::RESOLUTION) {
            return $this->paginateResolutions($filters, $perPage);
        }

        if ($documentType === DocumentType::ORDINANCE) {
            return $this->paginateOrdinances($filters, $perPage);
        }

        return $this->paginateMerged($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function paginateResolutions(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->resolutions
            ->paginateDocuments($filters, $perPage)
            ->through(fn (ResolutionItem $item) => $this->stripSortKeys($this->fromResolution($item)));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function paginateOrdinances(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->ordinances
            ->paginate($this->ordinanceFilters($filters), $perPage)
            ->through(fn (Ordinance $ordinance) => $this->stripSortKeys($this->fromOrdinance($ordinance)));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function paginateMerged(array $filters, int $perPage): LengthAwarePaginator
    {
        $items = $this->collectMerged($filters)
            ->sort($this->sortDocuments(...))
            ->values();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $total = $items->count();
        $slice = $items
            ->slice(($page - 1) * $perPage, $perPage)
            ->map(fn (array $document) => $this->stripSortKeys($document))
            ->values();

        return new Paginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function collectMerged(array $filters): Collection
    {
        $items = collect();

        if (! $this->hasResolutionOnlyFilters($filters)) {
            $items = $items->concat(
                $this->ordinances
                    ->collect($this->ordinanceFilters($filters))
                    ->map(fn (Ordinance $ordinance) => $this->fromOrdinance($ordinance)),
            );
        }

        return $items->concat(
            $this->resolutions
                ->collectDocuments($filters)
                ->map(fn (ResolutionItem $item) => $this->fromResolution($item)),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function ordinanceFilters(array $filters): array
    {
        return array_intersect_key($filters, array_flip([
            'number',
            'title',
            'series',
            'keyword',
            'date_from',
            'date_to',
            'has_pdf',
            'page',
        ]));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function hasResolutionOnlyFilters(array $filters): bool
    {
        foreach (self::RESOLUTION_ONLY_FILTERS as $key) {
            if (! empty($filters[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fromResolution(ResolutionItem $item): array
    {
        $document = $this->resolutions->documentToArray($item);
        $document['sort_series'] = (int) $item->series;
        $document['sort_number'] = $this->numericSortValue($item->resolutionNo);

        return $document;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fromOrdinance(Ordinance $ordinance): array
    {
        $document = $this->ordinances->toDashboardArray($ordinance);
        $document['sort_series'] = (int) $ordinance->series_year;
        $document['sort_number'] = (int) $ordinance->ordinance_no;

        return $document;
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    protected function sortDocuments(array $left, array $right): int
    {
        $seriesCompare = ((int) ($right['sort_series'] ?? 0)) <=> ((int) ($left['sort_series'] ?? 0));

        if ($seriesCompare !== 0) {
            return $seriesCompare;
        }

        return ((int) ($right['sort_number'] ?? 0)) <=> ((int) ($left['sort_number'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    protected function stripSortKeys(array $document): array
    {
        unset($document['sort_series'], $document['sort_number']);

        return $document;
    }

    protected function numericSortValue(?string $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (preg_match('/\d+/', $value, $matches) !== 1) {
            return 0;
        }

        return (int) $matches[0];
    }
}
