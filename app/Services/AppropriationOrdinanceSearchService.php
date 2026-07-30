<?php

namespace App\Services;

use App\Models\AppropriationOrdinance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AppropriationOrdinanceSearchService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->applyFilters(AppropriationOrdinance::query(), $filters)
            ->ordered()
            ->paginate(
                $perPage,
                ['*'],
                'page',
                max(1, (int) ($filters['page'] ?? 1)),
            );
    }

    /**
     * @param  Builder<AppropriationOrdinance>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<AppropriationOrdinance>
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['q'])) {
            $query->search((string) $filters['q']);
        }

        if (! empty($filters['series'])) {
            $query->where('series_year', (int) $filters['series']);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(AppropriationOrdinance $record): array
    {
        $pdfUrl = $record->pdfPublicUrl();

        return [
            'id' => $record->id,
            'number' => $record->displayNumber(),
            'series' => (string) $record->series_year,
            'series_label' => $record->displaySeries(),
            'subject' => trim((string) ($record->subject ?? '')),
            'url' => route('appropriation-ordinances.show', $record),
            'has_pdf' => filled($pdfUrl),
            'pdf_url' => $pdfUrl,
            'date_received' => $record->date_received?->toDateString(),
            'date_passed' => $record->date_passed?->toDateString(),
            'date_approved' => $record->date_approved?->toDateString(),
        ];
    }
}
