<?php

namespace App\Services;

use App\Data\ResolutionItem;
use App\Models\Legacy\LegacyCategory1;
use App\Models\Legacy\LegacyCategory2;
use App\Models\Legacy\LegacyCategory3;
use App\Models\Legacy\LegacyCategory4;
use App\Models\Legacy\LegacyDepartment;
use App\Models\Legacy\LegacyMunicipality;
use App\Models\Municipality;
use App\Models\Legacy\SpResolution;
use App\Models\Resolution;
use App\Support\DocumentType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResolutionRepository
{
    public function __construct(
        protected PdfAttachmentService $pdfService,
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->paginateDocuments($filters, $perPage);
    }

    public function paginateDocuments(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Resolution $r) => $this->map($r));
    }

    public function documentToArray(ResolutionItem $item): array
    {
        return [
            'id' => $item->id,
            'number' => $item->resolutionNo,
            'title' => $item->resolutionTitle,
            'author' => $item->sponsoredBy,
            'committee' => $item->committee,
            'keyword' => $item->keyword,
            'date' => $item->dateApproved,
            'series' => $item->series,
            'status' => $item->status,
            'category' => $item->categoryLabel,
            'department' => $item->departmentLabel,
            'municipality' => $item->municipalityLabel,
            'document_type' => $item->documentType,
            'document_type_label' => $item->documentTypeLabel ?? DocumentType::label($item->documentType),
            'document_type_badge_class' => $item->documentTypeBadgeClass ?? DocumentType::badgeClass($item->documentType),
            'has_pdf' => $item->hasPdf,
            'url' => route('resolutions.show', $item->id),
            'pdf_url' => $item->pdfUrl,
        ];
    }

    protected function baseQuery(array $filters): Builder
    {
        return $this->filteredQuery($filters)
            ->with(['category', 'department', 'municipality'])
            ->orderByDesc('series')
            ->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Resolution>
     */
    public function filteredQuery(array $filters): Builder
    {
        return $this->applyDocumentFilters(Resolution::query(), $filters);
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return Collection<int|string, Resolution>
     */
    public function loadByIds(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Resolution::query()
            ->with(['category', 'department', 'municipality'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    public function mapModel(Resolution $resolution): ResolutionItem
    {
        return $this->map($resolution);
    }

    /**
     * @param  Builder<Resolution>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Resolution>
     */
    protected function applyDocumentFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('resolution_no', 'like', $term)
                    ->orWhere('resolution_title', 'like', $term)
                    ->orWhere('keyword', 'like', $term);
            });
        }

        if (! empty($filters['number'])) {
            $query->where('resolution_no', 'like', '%'.$filters['number'].'%');
        }

        if (! empty($filters['title'])) {
            $query->where('resolution_title', 'like', '%'.$filters['title'].'%');
        }

        if (! empty($filters['author'])) {
            $query->where('sponsored_by', 'like', '%'.$filters['author'].'%');
        }

        if (! empty($filters['committee'])) {
            $query->where('committee', 'like', '%'.$filters['committee'].'%');
        }

        if (! empty($filters['keyword'])) {
            $query->where('keyword', 'like', '%'.$filters['keyword'].'%');
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('date_approved', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('date_approved', '<=', $filters['date_to']);
        }

        if (! empty($filters['series'])) {
            $query->where('series', $filters['series']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['municipality_id'])) {
            if ((string) $filters['municipality_id'] === 'bataan') {
                $bataanId = Municipality::query()
                    ->whereRaw('LOWER(description) = ?', ['bataan'])
                    ->value('id');

                $query->where(function (Builder $municipalityQuery) use ($bataanId): void {
                    $municipalityQuery->whereNull('municipality_id');

                    if ($bataanId) {
                        $municipalityQuery->orWhere('municipality_id', $bataanId);
                    }
                });
            } else {
                $query->where('municipality_id', $filters['municipality_id']);
            }
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }

        if (! empty($filters['has_pdf'])) {
            $query->where(function (Builder $builder): void {
                $builder->where(function (Builder $pathQuery): void {
                    $pathQuery->whereNotNull('pdf_path')->where('pdf_path', '!=', '');
                })->orWhere(function (Builder $urlQuery): void {
                    $urlQuery->whereNotNull('sp_pdf_url')->where('sp_pdf_url', '!=', '');
                });
            });
        }

        return $query;
    }

    public function findLegacy(int $id): ?SpResolution
    {
        return SpResolution::query()->where('ID', $id)->first();
    }

    public function findByLegacyId(int $legacySpId): ?Resolution
    {
        return Resolution::query()
            ->with(['department', 'category', 'category2', 'category3', 'category4', 'municipality', 'creator'])
            ->where('legacy_sp_id', $legacySpId)
            ->first();
    }

    public function findNew(int $id): ?Resolution
    {
        return Resolution::with(['department', 'category', 'category2', 'category3', 'category4', 'municipality', 'creator'])->find($id);
    }

    public function legacyCount(): int
    {
        return Resolution::query()->whereNotNull('legacy_sp_id')->count();
    }

    public function newCount(): int
    {
        return Resolution::query()->whereNull('legacy_sp_id')->count();
    }

    public function totalCount(): int
    {
        return Resolution::query()->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ResolutionItem>
     */
    public function collectDocuments(array $filters = []): Collection
    {
        return $this->baseQuery($filters)
            ->get()
            ->map(fn (Resolution $r) => $this->map($r));
    }

    public function countBySeries(int $limit = 10): Collection
    {
        return Resolution::query()
            ->select('series', DB::raw('COUNT(*) as total'))
            ->groupBy('series')
            ->orderByDesc('series')
            ->limit($limit)
            ->get();
    }

    protected function map(Resolution $r): ResolutionItem
    {
        $documentType = $r->document_type ?: DocumentType::RESOLUTION;

        return new ResolutionItem(
            source: $r->legacy_sp_id ? 'legacy' : 'new',
            id: $r->id,
            resolutionNo: $r->resolution_no,
            resolutionTitle: $r->resolution_title,
            series: $r->series,
            sponsoredBy: $r->sponsored_by,
            dateApproved: $r->date_approved?->format('Y-m-d'),
            categoryLabel: $r->category?->description,
            committee: $r->committee,
            keyword: $r->keyword,
            departmentLabel: $r->department?->description,
            municipalityLabel: $r->municipality?->description,
            documentType: $documentType,
            documentTypeLabel: DocumentType::label($documentType),
            documentTypeBadgeClass: DocumentType::badgeClass($documentType),
            hasPdf: $this->pdfService->hasLinkedPdf($r),
            pdfUrl: $this->pdfService->publicUrl($r),
            status: $r->status,
        );
    }

    public function legacyLookupLabels(SpResolution $r): array
    {
        return [
            'category' => $r->Category ? LegacyCategory1::query()->where('ID', $r->Category)->value('Desc') : null,
            'sub_cat1' => $r->Sub_Cat1 ? LegacyCategory2::query()->where('ID', $r->Sub_Cat1)->value('Desc') : null,
            'sub_cat2' => $r->Sub_Cat2 ? LegacyCategory3::query()->where('ID', $r->Sub_Cat2)->value('Desc') : null,
            'sub_cat3' => $r->Sub_Cat3 ? LegacyCategory4::query()->where('ID', $r->Sub_Cat3)->value('Desc') : null,
            'office' => $r->Office ? LegacyDepartment::query()->where('Code', $r->Office)->value('Desc') : null,
            'municipality' => $r->Municipality ? LegacyMunicipality::query()->where('Code', $r->Municipality)->value('Desc') : null,
        ];
    }
}
