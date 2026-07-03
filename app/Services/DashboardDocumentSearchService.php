<?php

namespace App\Services;

use App\Data\ResolutionItem;
use App\Models\Ordinance;
use App\Support\DocumentType;
use App\Support\MergedDocumentSql;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
            ->through(fn (ResolutionItem $item) => $this->fromResolution($item));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function paginateOrdinances(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->ordinances
            ->paginate($this->ordinanceFilters($filters), $perPage)
            ->through(fn (Ordinance $ordinance) => $this->fromOrdinance($ordinance));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function paginateMerged(array $filters, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $includeOrdinances = ! $this->hasResolutionOnlyFilters($filters);
        $includeResolutions = $this->shouldIncludeResolutionsInMerge($filters);

        if ($includeOrdinances && ! $includeResolutions) {
            return $this->paginateOrdinances($filters, $perPage);
        }

        if (! $includeOrdinances && $includeResolutions) {
            return $this->paginateResolutions($filters, $perPage);
        }

        $ordinanceFilters = $this->ordinanceFilters($filters);
        $unionQuery = $this->buildMergedUnionQuery($ordinanceFilters, $filters, $includeOrdinances, $includeResolutions);
        $total = $this->mergedTotalCount($ordinanceFilters, $filters, $includeOrdinances, $includeResolutions);
        $offset = ($page - 1) * $perPage;

        $pageRows = DB::query()
            ->fromSub($unionQuery, 'merged_documents')
            ->orderByDesc('sort_series')
            ->orderByDesc('sort_number')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $documents = $this->hydrateMergedPage($pageRows);

        return new Paginator(
            $documents,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @param  array<string, mixed>  $ordinanceFilters
     * @param  array<string, mixed>  $resolutionFilters
     */
    protected function buildMergedUnionQuery(
        array $ordinanceFilters,
        array $resolutionFilters,
        bool $includeOrdinances,
        bool $includeResolutions,
    ): \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder {
        $unionQuery = null;

        if ($includeOrdinances) {
            $ordinanceQuery = $this->ordinances
                ->filteredQuery($ordinanceFilters)
                ->selectRaw("'ordinance' as document_source, ordinances.id as id, ordinances.series_year as sort_series, ordinances.ordinance_no as sort_number");

            $unionQuery = $ordinanceQuery;
        }

        if ($includeResolutions) {
            $sortExpression = MergedDocumentSql::resolutionSortNumberColumn();
            $resolutionQuery = $this->resolutions
                ->filteredQuery($resolutionFilters)
                ->selectRaw("'resolution' as document_source, resolutions.id as id, resolutions.series as sort_series, {$sortExpression} as sort_number");

            $unionQuery = $unionQuery ? $unionQuery->unionAll($resolutionQuery) : $resolutionQuery;
        }

        return $unionQuery;
    }

    /**
     * @param  array<string, mixed>  $ordinanceFilters
     * @param  array<string, mixed>  $resolutionFilters
     */
    protected function mergedTotalCount(
        array $ordinanceFilters,
        array $resolutionFilters,
        bool $includeOrdinances,
        bool $includeResolutions,
    ): int {
        $total = 0;

        if ($includeOrdinances) {
            $total += $this->ordinances->filteredQuery($ordinanceFilters)->count();
        }

        if ($includeResolutions) {
            $total += $this->resolutions->filteredQuery($resolutionFilters)->count();
        }

        return $total;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $pageRows
     * @return Collection<int, array<string, mixed>>
     */
    protected function hydrateMergedPage(Collection $pageRows): Collection
    {
        $ordinanceIds = $pageRows
            ->where('document_source', 'ordinance')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $resolutionIds = $pageRows
            ->where('document_source', 'resolution')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $ordinances = $this->ordinances->loadByIds($ordinanceIds);
        $resolutions = $this->resolutions->loadByIds($resolutionIds);

        return $pageRows
            ->map(function (object $row) use ($ordinances, $resolutions): ?array {
                if ($row->document_source === 'ordinance') {
                    $ordinance = $ordinances->get((int) $row->id);

                    return $ordinance ? $this->fromOrdinance($ordinance) : null;
                }

                $resolution = $resolutions->get((int) $row->id);

                return $resolution ? $this->fromResolution($this->resolutions->mapModel($resolution)) : null;
            })
            ->filter()
            ->values();
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

    protected function shouldIncludeResolutionsInMerge(array $filters): bool
    {
        return (string) ($filters['document_type'] ?? '') !== DocumentType::ORDINANCE;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fromResolution(ResolutionItem $item): array
    {
        return $this->resolutions->documentToArray($item);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fromOrdinance(Ordinance $ordinance): array
    {
        return $this->ordinances->toDashboardArray($ordinance);
    }
}
