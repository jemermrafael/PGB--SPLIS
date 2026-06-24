<?php

namespace App\Services;

use App\Data\ResolutionItem;
use App\Models\Legacy\LegacyCategory1;
use App\Models\Legacy\LegacyCategory2;
use App\Models\Legacy\LegacyCategory3;
use App\Models\Legacy\LegacyCategory4;
use App\Models\Legacy\LegacyDepartment;
use App\Models\Legacy\LegacyMunicipality;
use App\Models\Legacy\SpResolution;
use App\Models\Resolution;
use App\Support\DocumentType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        if (! empty($filters['has_pdf'])) {
            return $this->paginateWithPdfFilter($filters, $perPage);
        }

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
            'pdf_url' => $item->hasPdf
                ? route('resolutions.pdf', ['series' => $item->series, 'resolutionNo' => $item->resolutionNo])
                : null,
        ];
    }

    protected function paginateWithPdfFilter(array $filters, int $perPage): LengthAwarePaginator
    {
        $items = $this->baseQuery($filters)
            ->get()
            ->map(fn (Resolution $r) => $this->map($r))
            ->filter(fn (ResolutionItem $item) => $item->hasPdf)
            ->values();

        $page = max(1, (int) ($filters['page'] ?? request()->integer('page', 1)));
        $total = $items->count();
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    protected function baseQuery(array $filters)
    {
        $query = Resolution::query()->with(['category', 'department', 'municipality']);

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
            $query->where('municipality_id', $filters['municipality_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }

        return $query->orderByDesc('series')->orderByDesc('id');
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
            hasPdf: $this->pdfService->existsFor($r),
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
