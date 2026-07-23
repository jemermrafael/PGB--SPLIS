<?php

namespace App\Http\Controllers;

use App\Models\AgendaItem;
use App\Models\BoardMemberCommitteeReport;
use App\Models\Committee;
use App\Models\User;
use App\Services\BoardMemberCommitteeReportService;
use App\Services\BoardMemberDashboardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BoardMemberCommitteeReportController extends Controller
{
    public function __construct(
        protected BoardMemberCommitteeReportService $reports,
        protected BoardMemberDashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BoardMemberCommitteeReport::class);

        /** @var User $user */
        $user = $request->user();

        $reports = BoardMemberCommitteeReport::query()
            ->where('board_member_id', $user->board_member_id)
            ->with(['agendaItems:id,tracking_no,title'])
            ->orderByDesc('submitted_at')
            ->paginate(20);

        return view('board-member.committee-reports.index', [
            'reports' => $reports,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', BoardMemberCommitteeReport::class);

        /** @var User $user */
        $user = $request->user();

        $chairCommittees = $this->chairCommitteesFor($user);
        $filters = $this->resolvedFilters($request, $chairCommittees);

        return view('board-member.committee-reports.create', [
            'q' => $filters['q'],
            'committeeId' => $filters['committee_id'],
            'chairCommittees' => $chairCommittees,
            'agendaItems' => $this->filteredAgendaItems($user, $filters['q'], $filters['committee']),
            'selectedAgendaIds' => old('agenda_item_ids', []),
            'agendaSearchUrl' => route('board-member.committee-reports.agendas'),
        ]);
    }

    public function edit(Request $request, BoardMemberCommitteeReport $committeeReport): View
    {
        $this->authorize('update', $committeeReport);

        /** @var User $user */
        $user = $request->user();

        $committeeReport->load(['agendaItems:id,tracking_no,title,committee_referred']);
        $chairCommittees = $this->chairCommitteesFor($user);
        $filters = $this->resolvedFilters($request, $chairCommittees);
        $selectedIds = collect(old(
            'agenda_item_ids',
            $committeeReport->agendaItems->pluck('id')->all(),
        ))->map(fn ($id) => (int) $id)->all();

        return view('board-member.committee-reports.edit', [
            'report' => $committeeReport,
            'q' => $filters['q'],
            'committeeId' => $filters['committee_id'],
            'chairCommittees' => $chairCommittees,
            'agendaItems' => $this->filteredAgendaItems(
                $user,
                $filters['q'],
                $filters['committee'],
                $committeeReport,
            ),
            'selectedAgendaIds' => $selectedIds,
            'agendaSearchUrl' => route('board-member.committee-reports.agendas', [
                'report_id' => $committeeReport->id,
            ]),
        ]);
    }

    public function agendas(Request $request): JsonResponse
    {
        $this->authorize('create', BoardMemberCommitteeReport::class);

        /** @var User $user */
        $user = $request->user();
        $chairCommittees = $this->chairCommitteesFor($user);
        $filters = $this->resolvedFilters($request, $chairCommittees);
        $existingReport = $this->reportForAgendaSearch($request, $user);
        $items = $this->filteredAgendaItems(
            $user,
            $filters['q'],
            $filters['committee'],
            $existingReport,
        );

        return response()->json([
            'data' => $items->map(fn (AgendaItem $agenda) => [
                'id' => $agenda->id,
                'number' => $agenda->listNumberLabel(),
                'title' => $agenda->title ?: 'Untitled',
                'committee' => $agenda->committee_referred,
            ])->values(),
            'meta' => [
                'q' => $filters['q'],
                'committee_id' => $filters['committee_id'],
                'total' => $items->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', BoardMemberCommitteeReport::class);

        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'agenda_item_ids' => ['nullable', 'array'],
            'agenda_item_ids.*' => ['integer', 'exists:agenda_items,id'],
        ]);

        $this->reports->store(
            $user,
            $validated['pdf'],
            $validated['title'] ?? null,
            $validated['agenda_item_ids'] ?? [],
        );

        return redirect()
            ->route('board-member.committee-reports.index')
            ->with('status', 'Committee Report submitted. Tagged Agenda items and related Session folders were updated.');
    }

    public function update(Request $request, BoardMemberCommitteeReport $committeeReport): RedirectResponse
    {
        $this->authorize('update', $committeeReport);

        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'agenda_item_ids' => ['nullable', 'array'],
            'agenda_item_ids.*' => ['integer', 'exists:agenda_items,id'],
        ]);

        $this->reports->update(
            $user,
            $committeeReport,
            $validated['pdf'] ?? null,
            $validated['title'] ?? null,
            $validated['agenda_item_ids'] ?? [],
        );

        return redirect()
            ->route('board-member.committee-reports.index')
            ->with('status', 'Committee report updated.');
    }

    public function destroy(Request $request, BoardMemberCommitteeReport $committeeReport): RedirectResponse
    {
        $this->authorize('delete', $committeeReport);

        /** @var User $user */
        $user = $request->user();

        $this->reports->delete($user, $committeeReport);

        return redirect()
            ->route('board-member.committee-reports.index')
            ->with('status', 'Committee report deleted.');
    }

    public function pdf(Request $request, BoardMemberCommitteeReport $committeeReport): StreamedResponse
    {
        $this->authorize('view', $committeeReport);

        return $this->reports->streamPdf($committeeReport);
    }

    /**
     * @return Collection<int, Committee>
     */
    protected function chairCommitteesFor(User $user): Collection
    {
        return $this->dashboard->assignmentsGroupedByRole($user)['chair']
            ->pluck('committee')
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, Committee>  $chairCommittees
     * @return array{q: string, committee_id: int|null, committee: Committee|null}
     */
    protected function resolvedFilters(Request $request, Collection $chairCommittees): array
    {
        $q = trim((string) $request->input('q', ''));
        $committeeId = $request->integer('committee_id') ?: null;
        $selectedCommittee = $committeeId
            ? $chairCommittees->first(fn (Committee $committee) => (int) $committee->id === $committeeId)
            : null;

        if ($committeeId !== null && $selectedCommittee === null) {
            $committeeId = null;
        }

        return [
            'q' => $q,
            'committee_id' => $committeeId,
            'committee' => $selectedCommittee,
        ];
    }

    protected function reportForAgendaSearch(Request $request, User $user): ?BoardMemberCommitteeReport
    {
        $reportId = $request->integer('report_id') ?: null;
        if ($reportId === null) {
            return null;
        }

        $report = BoardMemberCommitteeReport::query()->find($reportId);
        if ($report === null || (int) $report->board_member_id !== (int) $user->board_member_id) {
            return null;
        }

        $this->authorize('update', $report);

        return $report;
    }

    /**
     * @return Collection<int, AgendaItem>
     */
    protected function filteredAgendaItems(
        User $user,
        string $q,
        ?Committee $committee,
        ?BoardMemberCommitteeReport $existingReport = null,
    ): Collection {
        $includeIds = $existingReport
            ? $existingReport->agendaItems()->pluck('agenda_items.id')->map(fn ($id) => (int) $id)->all()
            : [];

        /** @var Builder<AgendaItem> $agendaQuery */
        $agendaQuery = $this->dashboard->chairmanshipAgendaQueryFor($user)
            ->where(function (Builder $query) use ($includeIds): void {
                $query->where(function (Builder $open): void {
                    $open->where(function (Builder $pdf): void {
                        $pdf->whereNull('committee_report_pdf_path')
                            ->orWhere('committee_report_pdf_path', '');
                    })->where(function (Builder $url): void {
                        $url->whereNull('committee_report_url')
                            ->orWhere('committee_report_url', '');
                    });
                });

                if ($includeIds !== []) {
                    $query->orWhereIn('id', $includeIds);
                }
            })
            ->orderByDesc('date_of_referral')
            ->orderByDesc('date_received')
            ->orderByDesc('id');

        if ($committee !== null) {
            $agendaQuery->where('committee_referred', 'like', '%'.$committee->name.'%');
        }

        if ($q !== '') {
            $agendaQuery->where(function ($query) use ($q): void {
                $query->where('tracking_no', 'like', '%'.$q.'%')
                    ->orWhere('title', 'like', '%'.$q.'%');
            });
        }

        return $agendaQuery
            ->limit(80)
            ->get(['id', 'tracking_no', 'title', 'committee_referred', 'status']);
    }
}
