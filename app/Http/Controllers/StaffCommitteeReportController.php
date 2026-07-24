<?php

namespace App\Http\Controllers;

use App\Enums\CommitteeMembershipRole;
use App\Models\AgendaItem;
use App\Models\BoardMember;
use App\Models\BoardMemberCommitteeReport;
use App\Models\Committee;
use App\Models\CommitteeMembership;
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

class StaffCommitteeReportController extends Controller
{
    public function __construct(
        protected BoardMemberCommitteeReportService $reports,
        protected BoardMemberDashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BoardMemberCommitteeReport::class);
        abort_unless($request->user()?->canEncode(), 403);

        return view('committee-reports.index', [
            'committees' => Committee::query()->active()->ordered()->get(['id', 'name']),
            'searchUrl' => route('committee-reports.search'),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BoardMemberCommitteeReport::class);
        abort_unless($request->user()?->canEncode(), 403);

        /** @var User $user */
        $user = $request->user();

        $query = BoardMemberCommitteeReport::query()
            ->with([
                'boardMember',
                'submitter:id,name,role',
                'agendaItems:id,tracking_no,title,committee_referred',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        $q = trim((string) $request->input('q', ''));
        if ($q !== '') {
            $query->where(function (Builder $builder) use ($q): void {
                $builder->where('title', 'like', '%'.$q.'%')
                    ->orWhere('original_filename', 'like', '%'.$q.'%')
                    ->orWhereHas('boardMember', function (Builder $member) use ($q): void {
                        $member->where('name', 'like', '%'.$q.'%')
                            ->orWhere('honorific', 'like', '%'.$q.'%');
                    });
            });
        }

        $committeeId = $request->integer('committee_id') ?: null;
        if ($committeeId) {
            $committee = Committee::query()->find($committeeId);
            if ($committee !== null) {
                $query->whereHas('agendaItems', function (Builder $agenda) use ($committee): void {
                    $agenda->where('committee_referred', 'like', '%'.$committee->name.'%');
                });
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('submitted_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('submitted_at', '<=', $request->date('date_to'));
        }

        $paginator = $query->paginate(20);

        return response()->json([
            'data' => collect($paginator->items())->map(function (BoardMemberCommitteeReport $report) use ($user) {
                return [
                    'id' => $report->id,
                    'title' => $report->title ?: '—',
                    'filename' => $report->original_filename,
                    'submitted_at' => $report->submitted_at?->toIso8601String(),
                    'submitted_at_label' => $report->submitted_at?->format('M j, Y g:i A') ?? '—',
                    'board_member' => $report->boardMember?->displayName() ?? '—',
                    'submitted_by' => $report->submitter?->name ?? '—',
                    'submitted_by_role' => $report->submitter?->role?->label() ?? null,
                    'agendas' => $report->agendaItems->map(fn (AgendaItem $agenda) => [
                        'id' => $agenda->id,
                        'label' => $agenda->displayLabel(),
                        'url' => route('agenda.show', $agenda),
                        'committee' => $agenda->committee_referred,
                    ])->values(),
                    'pdf_url' => route('committee-reports.pdf', $report),
                    'can_update' => $user->can('update', $report),
                    'can_delete' => $user->can('delete', $report),
                    'edit_url' => $user->can('update', $report)
                        ? route('committee-reports.edit', $report)
                        : null,
                    'delete_url' => $user->can('delete', $report)
                        ? route('committee-reports.destroy', $report)
                        : null,
                ];
            })->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', BoardMemberCommitteeReport::class);
        abort_unless($request->user()?->canEncode(), 403);

        $chairMembers = $this->chairBoardMembers();
        $boardMemberId = $request->integer('board_member_id') ?: null;
        $selectedMember = $boardMemberId
            ? $chairMembers->first(fn (BoardMember $member) => (int) $member->id === $boardMemberId)
            : null;

        $chairCommittees = $selectedMember
            ? $this->dashboard->chairCommitteesForBoardMember((int) $selectedMember->id)
            : collect();
        $filters = $this->resolvedFilters($request, $chairCommittees);

        return view('committee-reports.create', [
            'chairMembers' => $chairMembers,
            'boardMemberId' => $selectedMember?->id,
            'q' => $filters['q'],
            'committeeId' => $filters['committee_id'],
            'chairCommittees' => $chairCommittees,
            'agendaItems' => $selectedMember
                ? $this->filteredAgendaItems((int) $selectedMember->id, $filters['q'], $filters['committee'])
                : collect(),
            'selectedAgendaIds' => old('agenda_item_ids', []),
            'agendaSearchUrl' => route('committee-reports.agendas'),
        ]);
    }

    public function edit(Request $request, BoardMemberCommitteeReport $committeeReport): View
    {
        $this->authorize('update', $committeeReport);
        abort_unless($request->user()?->canEncode(), 403);

        $committeeReport->load(['agendaItems:id,tracking_no,title,committee_referred', 'boardMember']);
        $chairCommittees = $this->dashboard->chairCommitteesForBoardMember((int) $committeeReport->board_member_id);
        $filters = $this->resolvedFilters($request, $chairCommittees);
        $selectedIds = collect(old(
            'agenda_item_ids',
            $committeeReport->agendaItems->pluck('id')->all(),
        ))->map(fn ($id) => (int) $id)->all();

        return view('committee-reports.edit', [
            'report' => $committeeReport,
            'boardMemberId' => $committeeReport->board_member_id,
            'q' => $filters['q'],
            'committeeId' => $filters['committee_id'],
            'chairCommittees' => $chairCommittees,
            'agendaItems' => $this->filteredAgendaItems(
                (int) $committeeReport->board_member_id,
                $filters['q'],
                $filters['committee'],
                $committeeReport,
            ),
            'selectedAgendaIds' => $selectedIds,
            'agendaSearchUrl' => route('committee-reports.agendas', [
                'report_id' => $committeeReport->id,
                'board_member_id' => $committeeReport->board_member_id,
            ]),
        ]);
    }

    public function agendas(Request $request): JsonResponse
    {
        $this->authorize('create', BoardMemberCommitteeReport::class);
        abort_unless($request->user()?->canEncode(), 403);

        $boardMemberId = $request->integer('board_member_id') ?: null;
        $existingReport = null;

        if ($request->filled('report_id')) {
            $existingReport = BoardMemberCommitteeReport::query()->find($request->integer('report_id'));
            if ($existingReport !== null) {
                $this->authorize('update', $existingReport);
                $boardMemberId = (int) $existingReport->board_member_id;
            }
        }

        if (! $boardMemberId) {
            return response()->json([
                'data' => [],
                'meta' => ['q' => '', 'committee_id' => null, 'total' => 0],
            ]);
        }

        $chairCommittees = $this->dashboard->chairCommitteesForBoardMember($boardMemberId);
        $filters = $this->resolvedFilters($request, $chairCommittees);
        $items = $this->filteredAgendaItems(
            $boardMemberId,
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
        abort_unless($request->user()?->canEncode(), 403);

        $validated = $request->validate([
            'board_member_id' => ['required', 'integer', 'exists:board_members,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'agenda_item_ids' => ['nullable', 'array'],
            'agenda_item_ids.*' => ['integer', 'exists:agenda_items,id'],
        ]);

        $this->reports->store(
            $request->user(),
            $validated['pdf'],
            $validated['title'] ?? null,
            $validated['agenda_item_ids'] ?? [],
            (int) $validated['board_member_id'],
        );

        return redirect()
            ->route('committee-reports.index')
            ->with('status', 'Committee Report submitted. Tagged Agenda items and related Session folders were updated.');
    }

    public function update(Request $request, BoardMemberCommitteeReport $committeeReport): RedirectResponse
    {
        $this->authorize('update', $committeeReport);
        abort_unless($request->user()?->canEncode(), 403);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'agenda_item_ids' => ['nullable', 'array'],
            'agenda_item_ids.*' => ['integer', 'exists:agenda_items,id'],
        ]);

        $this->reports->update(
            $request->user(),
            $committeeReport,
            $validated['pdf'] ?? null,
            $validated['title'] ?? null,
            $validated['agenda_item_ids'] ?? [],
        );

        return redirect()
            ->route('committee-reports.index')
            ->with('status', 'Committee report updated.');
    }

    public function destroy(Request $request, BoardMemberCommitteeReport $committeeReport): RedirectResponse
    {
        $this->authorize('delete', $committeeReport);
        abort_unless($request->user()?->canEncode(), 403);

        $this->reports->delete($request->user(), $committeeReport);

        return redirect()
            ->route('committee-reports.index')
            ->with('status', 'Committee report deleted.');
    }

    public function pdf(Request $request, BoardMemberCommitteeReport $committeeReport): StreamedResponse
    {
        $this->authorize('view', $committeeReport);
        abort_unless($request->user()?->canEncode() || $request->user()?->isBoardMember(), 403);

        return $this->reports->streamPdf($committeeReport);
    }

    /**
     * @return Collection<int, BoardMember>
     */
    protected function chairBoardMembers(): Collection
    {
        $term = $this->dashboard->resolveTerm();

        $ids = CommitteeMembership::query()
            ->where('committee_term_id', $term->id)
            ->where('role', CommitteeMembershipRole::Chair)
            ->pluck('board_member_id')
            ->unique()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return BoardMember::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->ordered()
            ->get();
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

    /**
     * @return Collection<int, AgendaItem>
     */
    protected function filteredAgendaItems(
        int $boardMemberId,
        string $q,
        ?Committee $committee,
        ?BoardMemberCommitteeReport $existingReport = null,
    ): Collection {
        $includeIds = $existingReport
            ? $existingReport->agendaItems()->pluck('agenda_items.id')->map(fn ($id) => (int) $id)->all()
            : [];

        /** @var Builder<AgendaItem> $agendaQuery */
        $agendaQuery = $this->dashboard->chairmanshipAgendaQueryForBoardMember($boardMemberId)
            ->where(function (Builder $query) use ($includeIds): void {
                $query->where(function (Builder $open): void {
                    $open->where('status', '!=', AgendaItem::STATUS_DONE)
                        ->where(function (Builder $pdf): void {
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
