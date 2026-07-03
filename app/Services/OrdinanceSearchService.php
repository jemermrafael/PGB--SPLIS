<?php

namespace App\Services;

use App\Models\Ordinance;
use App\Support\DocumentType;
use App\Support\OrdinanceNumberParser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OrdinanceSearchService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->query($filters)
            ->ordered()
            ->paginate(
                $perPage,
                ['*'],
                'page',
                max(1, (int) ($filters['page'] ?? 1)),
            );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Ordinance>
     */
    public function collect(array $filters): Collection
    {
        return $this->query($filters)->ordered()->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Ordinance>
     */
    public function query(array $filters): Builder
    {
        return $this->filteredQuery($filters)->with('authoredSponsoredMembers');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Ordinance>
     */
    public function filteredQuery(array $filters): Builder
    {
        return $this->applyFilters(Ordinance::query(), $filters);
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return Collection<int|string, Ordinance>
     */
    public function loadByIds(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Ordinance::query()
            ->with('authoredSponsoredMembers')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Builder<Ordinance>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Ordinance>
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        $number = trim((string) ($filters['number'] ?? ''));

        if ($number !== '') {
            $ordinanceNo = OrdinanceNumberParser::parse($number);

            if ($ordinanceNo !== null) {
                $query->where('ordinance_no', $ordinanceNo);
            } else {
                $query->whereRaw('CAST(ordinance_no AS CHAR) LIKE ?', ['%'.$number.'%']);
            }
        }

        $title = trim((string) ($filters['title'] ?? ''));

        if ($title !== '') {
            $this->applyTextSearch($query, $title);
        }

        $series = trim((string) ($filters['series'] ?? ''));

        if ($series !== '' && ctype_digit($series)) {
            $query->where('series_year', (int) $series);
        }

        $classification = trim((string) ($filters['classification'] ?? ''));

        if ($classification !== '') {
            $query->where('classification', $classification);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('date_enacted', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('date_enacted', '<=', $filters['date_to']);
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));

        if ($keyword !== '') {
            $this->applyTextSearch($query, $keyword);
        }

        if (! empty($filters['has_pdf'])) {
            $query->whereNotNull('pdf_url')->where('pdf_url', '!=', '');
        }

        $publicationStatus = trim((string) ($filters['publication_status'] ?? ''));

        if ($publicationStatus !== '') {
            $query->where('publication_status', $publicationStatus);
        }

        return $query;
    }

    protected function applyTextSearch(Builder $query, string $term): void
    {
        $ordinanceNo = OrdinanceNumberParser::parse($term);

        $query->where(function (Builder $query) use ($term, $ordinanceNo): void {
            $query->where('subject', 'like', "%{$term}%")
                ->orWhere('implementing_bodies', 'like', "%{$term}%")
                ->orWhere('remarks', 'like', "%{$term}%");

            if ($ordinanceNo !== null) {
                $query->orWhere('ordinance_no', $ordinanceNo);
            }

            if (ctype_digit($term)) {
                $query->orWhere('series_year', (int) $term);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Ordinance $ordinance): array
    {
        return [
            'id' => $ordinance->id,
            'number' => $ordinance->displayNumber(),
            'series' => (string) $ordinance->series_year,
            'series_label' => $ordinance->displaySeries(),
            'title' => $ordinance->subject ?? '',
            'url' => route('ordinances.show', $ordinance),
            'has_pdf' => filled($ordinance->pdf_url),
            'pdf_url' => $ordinance->pdf_url,
            'date' => $ordinance->date_enacted?->toDateString(),
            'date_approved' => $ordinance->date_approved?->toDateString(),
            'effectivity_date' => $ordinance->effectivity_date?->toDateString(),
            'classification' => $ordinance->classification,
            'authored_sponsored_by' => $ordinance->authoredSponsoredDisplay(),
            'publication_status' => $ordinance->publication_status?->value,
            'publication_status_label' => $ordinance->publicationStatusLabel(),
            'publication_status_badge_class' => $ordinance->publicationStatusBadgeClass(),
            'publication_status_button_class' => $ordinance->publication_status?->showButtonClass(),
            'publication_status_marker_class' => $ordinance->publicationStatusMarkerDotClass(),
            'publication_status_row_class' => $ordinance->publication_status?->rowClass(),
            'publication_status_icon_url' => $ordinance->publication_status
                ? asset($ordinance->publication_status->iconPath())
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDashboardArray(Ordinance $ordinance): array
    {
        return [
            'id' => $ordinance->id,
            'number' => $ordinance->displayNumber(),
            'series' => (string) $ordinance->series_year,
            'series_label' => $ordinance->displaySeries(),
            'title' => $ordinance->subject ?? '',
            'author' => $ordinance->authoredSponsoredDisplay(),
            'committee' => $ordinance->implementing_bodies,
            'keyword' => null,
            'date' => $ordinance->date_enacted?->format('Y-m-d') ?? $ordinance->date_approved?->format('Y-m-d'),
            'status' => $ordinance->classification,
            'category' => $ordinance->classification,
            'department' => null,
            'municipality' => null,
            'document_type' => DocumentType::ORDINANCE,
            'document_type_label' => DocumentType::label(DocumentType::ORDINANCE),
            'document_type_badge_class' => DocumentType::badgeClass(DocumentType::ORDINANCE),
            'has_pdf' => filled($ordinance->pdf_url),
            'url' => route('ordinances.show', $ordinance),
            'pdf_url' => $ordinance->pdf_url,
            'publication_status' => $ordinance->publication_status?->value,
            'publication_status_label' => $ordinance->publicationStatusLabel(),
            'publication_status_badge_class' => $ordinance->publicationStatusBadgeClass(),
            'publication_status_button_class' => $ordinance->publication_status?->showButtonClass(),
            'publication_status_icon_url' => $ordinance->publication_status
                ? asset($ordinance->publication_status->iconPath())
                : null,
        ];
    }
}
