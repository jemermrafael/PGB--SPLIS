<?php

namespace App\Services;

use App\Models\AppropriationOrdinance;
use App\Models\Ordinance;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MyOrdinanceService
{
    public function paginateAll(Request $request, int $perPage = 15): LengthAwarePaginator
    {
        return $this->paginateMerged($request, $perPage);
    }

    public function paginateForMember(Request $request, int $boardMemberId, int $perPage = 15): LengthAwarePaginator
    {
        $search = trim((string) $request->input('q', ''));
        $series = $request->filled('series') ? (int) $request->input('series') : null;

        $ordinances = Ordinance::query()
            ->with('boardMembers')
            ->forBoardMember($boardMemberId)
            ->when($series, fn ($query) => $query->where('series_year', $series))
            ->when($search !== '', fn ($query) => $query->search($search))
            ->orderByDesc('series_year')
            ->orderByDesc('ordinance_no')
            ->paginate($perPage)
            ->withQueryString();

        return $ordinances->through(fn (Ordinance $ordinance) => $this->fromOrdinance($ordinance));
    }

    public function paginate(Request $request, int $perPage = 15): LengthAwarePaginator
    {
        return $this->paginateAll($request, $perPage);
    }

    protected function paginateMerged(Request $request, int $perPage = 15): LengthAwarePaginator
    {
        $search = trim((string) $request->input('q', ''));
        $series = $request->filled('series') ? (int) $request->input('series') : null;
        $type = (string) $request->input('type', '');
        $hasAuthors = (bool) $request->boolean('has_authors');

        $ordinances = collect();
        if ($type === '' || $type === 'ordinance') {
            $ordinances = Ordinance::query()
                ->with('boardMembers')
                ->when($series, fn ($query) => $query->where('series_year', $series))
                ->when($search !== '', fn ($query) => $query->search($search))
                ->when($hasAuthors, fn ($query) => $query->whereHas('boardMembers'))
                ->get()
                ->map(fn (Ordinance $ordinance) => $this->fromOrdinance($ordinance));
        }

        $appropriations = collect();
        if ($type === '' || $type === 'appropriation_ordinance') {
            $appropriations = AppropriationOrdinance::query()
                ->when($series, fn ($query) => $query->where('series_year', $series))
                ->when($search !== '', fn ($query) => $query->search($search))
                ->get()
                ->map(fn (AppropriationOrdinance $ordinance) => $this->fromAppropriationOrdinance($ordinance));
        }

        $merged = $ordinances
            ->concat($appropriations)
            ->sortByDesc(fn (array $row) => sprintf('%04d-%05d', $row['series_year'], $row['ordinance_no']))
            ->values();

        $page = max(1, (int) $request->input('page', 1));
        $total = $merged->count();
        $items = $merged->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    /**
     * @return Collection<int, int>
     */
    public function seriesYearsForMember(int $boardMemberId): Collection
    {
        return Ordinance::query()
            ->forBoardMember($boardMemberId)
            ->select('series_year')
            ->distinct()
            ->orderByDesc('series_year')
            ->pluck('series_year');
    }

    /**
     * @return Collection<int, int>
     */
    public function seriesYears(): Collection
    {
        return Ordinance::query()
            ->select('series_year')
            ->distinct()
            ->pluck('series_year')
            ->merge(
                AppropriationOrdinance::query()
                    ->select('series_year')
                    ->distinct()
                    ->pluck('series_year'),
            )
            ->unique()
            ->sortDesc()
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function fromOrdinance(Ordinance $ordinance): array
    {
        return [
            'type' => 'ordinance',
            'type_label' => 'Ordinance',
            'ordinance_no' => $ordinance->ordinance_no,
            'series_year' => $ordinance->series_year,
            'number_label' => $ordinance->displayNumber(),
            'series_label' => $ordinance->displaySeries(),
            'subject' => $ordinance->subject,
            'date_received' => null,
            'date_passed' => $ordinance->date_enacted,
            'date_approved' => $ordinance->date_approved,
            'authors' => $ordinance->boardMembersAttributionDisplay(),
            'url' => route('ordinances.show', $ordinance),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fromAppropriationOrdinance(AppropriationOrdinance $ordinance): array
    {
        return [
            'type' => 'appropriation_ordinance',
            'type_label' => 'Appropriation Ordinance',
            'ordinance_no' => $ordinance->ordinance_no,
            'series_year' => $ordinance->series_year,
            'number_label' => $ordinance->displayNumber(),
            'series_label' => $ordinance->displaySeries(),
            'subject' => $ordinance->subject,
            'date_received' => $ordinance->date_received,
            'date_passed' => $ordinance->date_passed,
            'date_approved' => $ordinance->date_approved,
            'authors' => null,
            'url' => route('appropriation-ordinances.show', $ordinance),
        ];
    }
}
