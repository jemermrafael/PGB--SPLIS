<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\Resolution;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MyResolutionService
{
    public function __construct(
        protected BoardMemberDashboardService $dashboard,
        protected PdfAttachmentService $pdfService,
    ) {}

    public function paginateForUser(Request $request, User $user, int $perPage = 15): LengthAwarePaginator
    {
        $search = trim((string) $request->input('q', ''));
        $series = $request->filled('series') ? (int) $request->input('series') : null;

        $query = $this->baseQueryForUser($user)
            ->with(['publishedFromAgenda'])
            ->when($series, fn ($builder) => $builder->where('series', $series))
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner->where('resolution_no', 'like', '%'.$search.'%')
                        ->orWhere('resolution_title', 'like', '%'.$search.'%')
                        ->orWhere('sponsored_by', 'like', '%'.$search.'%')
                        ->orWhere('committee', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('series')
            ->orderByDesc('id');

        return $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Resolution $resolution) => $this->toArray($resolution));
    }

    /**
     * @return Collection<int, int>
     */
    public function seriesYearsForUser(User $user): Collection
    {
        return $this->baseQueryForUser($user)
            ->select('series')
            ->whereNotNull('series')
            ->distinct()
            ->orderByDesc('series')
            ->pluck('series');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Resolution>
     */
    public function baseQueryForUser(User $user)
    {
        if ($user->board_member_id === null) {
            return Resolution::query()->whereRaw('0 = 1');
        }

        $resolutionIds = $this->dashboard
            ->chairmanshipAgendaQueryFor($user)
            ->whereNotNull('resolution_id')
            ->pluck('resolution_id')
            ->unique()
            ->filter()
            ->values();

        if ($resolutionIds->isEmpty()) {
            return Resolution::query()->whereRaw('0 = 1');
        }

        return Resolution::query()->whereIn('id', $resolutionIds->all());
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(Resolution $resolution): array
    {
        $agenda = $resolution->publishedFromAgenda;
        $pdfUrl = $this->pdfService->publicUrl($resolution);

        return [
            'id' => $resolution->id,
            'number_label' => filled($resolution->resolution_no)
                ? (string) $resolution->resolution_no
                : 'Unnumbered',
            'series_label' => $resolution->series
                ? 'Series of '.$resolution->series
                : null,
            'series_year' => $resolution->series,
            'subject' => $resolution->resolution_title,
            'committee' => $resolution->committee,
            'date_approved' => $resolution->date_approved?->format('Y-m-d'),
            'agenda_label' => $agenda instanceof AgendaItem ? $agenda->displayLabel() : null,
            'agenda_url' => $agenda instanceof AgendaItem ? route('agenda.show', $agenda) : null,
            'url' => route('resolutions.show', $resolution),
            'has_pdf' => $this->pdfService->hasLinkedPdf($resolution),
            'pdf_url' => $pdfUrl,
        ];
    }
}
