<?php

namespace App\Http\Controllers;

use App\Enums\CommitteeMembershipRole;
use App\Models\AgendaItem;
use App\Models\BoardMember;
use App\Models\BoardMemberCommitteeReport;
use App\Models\Committee;
use App\Models\CommitteeMembership;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Services\BoardMemberCommitteeReportService;
use App\Services\BoardMemberDashboardService;
use App\Support\CommitteeLookup;
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
        abort_unless($request->user()?->hasModuleCapability(\App\Support\UserCapability::COMMITTEE_REPORTS), 403);

        return view('committee-reports.index', [
            'committees' => Committee::query()->active()->ordered()->get(['id', 'name']),
            'sessions' => LegislativeSession::query()
                ->whereNotNull('session_date')
                ->orderByDesc('session_date')
                ->orderByDesc('id')
                ->get(['id', 'session_number', 'session_date']),
            'searchUrl' => route('committee-reports.search'),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BoardMemberCommitteeReport::class);
        abort_unless($request->user()?->hasModuleCapability(\App\Support\UserCapability::COMMITTEE_REPORTS), 403);

        /** @var User $user */
        $user = $request->user();

        $query = BoardMemberCommitteeReport::query()
            ->with([
                'boardMember',
                'submitter:id,name,role',
                'agendaItems:id,tracking_no,title,committee_referred',
                'sessionFiles.session:id,session_number,session_date',
            ])
            ->orderByRaw('(
                select max(ls.session_date)
                from legislative_session_committee_report_files as f
                inner join legislative_sessions as ls on ls.id = f.legislative_session_id
                where f.board_member_committee_report_id = board_member_committee_reports.id
            ) desc')
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
                    CommitteeLookup::applyAgendaCommitteeFilter($agenda, $committee);
                });
            }
        }

        $sessionId = $request->integer('session_id') ?: null;
        if ($sessionId) {
            $query->whereHas('sessionFiles', function (Builder $files) use ($sessionId): void {
                $files->where('legislative_session_id', $sessionId);
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('submitted_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('submitted_at', '<=', $request->date('date_to'));
        }

        $paginator = $query->paginate(20);

        return response()->json([
            'data' => collect($paginator->items())->map(function (BoardMemberCommitteeReport $report) use ($user, $sessionId) {
                $linkedSessions = $report->sessionFiles
                    ->map(fn ($file) => $file->session)
                    ->filter()
                    ->unique('id')
                    ->sortByDesc(fn (LegislativeSession $session) => $session->session_date?->timestamp ?? 0)
                    ->values();

                if ($sessionId) {
                    $linkedSessions = $linkedSessions
                        ->filter(fn (LegislativeSession $item) => (int) $item->id === $sessionId)
                        ->values();
                }

                $sessionsPayload = $linkedSessions->map(fn (LegislativeSession $session) => [
                    'id' => $session->id,
                    'label' => $session->displayTitle(),
                    'date' => $session->session_date?->toDateString(),
                ])->values();

                return [
                    'id' => $report->id,
                    'title' => $report->title ?: ($report->original_filename ?: 'Committee Report'),
                    'filename' => $report->original_filename,
                    'submitted_at' => $report->submitted_at?->toIso8601String(),
                    'submitted_at_label' => $report->submitted_at?->format('M j, Y g:i A') ?? '—',
                    'board_member' => $report->boardMember?->displayName() ?? '—',
                    'submitted_by' => $report->submitter?->name ?? '—',
                    'submitted_by_role' => $report->submitter?->role?->label() ?? null,
                    'sessions' => $sessionsPayload,
                    // Primary/latest session (kept for compatibility).
                    'session' => $sessionsPayload->first(),
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
        abort_unless($request->user()?->hasModuleCapability(\App\Support\UserCapability::COMMITTEE_REPORTS), 403);

        $chairMembers = $this->chairBoardMembers();
        $selectionCommittees = $this->chairCommitteesForSelection();

        $requestedCommitteeId = $request->integer('committee_id') ?: null;
        $boardMemberId = $request->integer('board_member_id')
            ?: (int) old('board_member_id', 0)
            ?: null;

        if ($boardMemberId === null && $requestedCommitteeId) {
            $boardMemberId = $selectionCommittees
                ->first(fn (array $row) => (int) $row['id'] === $requestedCommitteeId)['board_member_id'] ?? null;
        }

        $selectedMember = $boardMemberId
            ? $chairMembers->first(fn (BoardMember $member) => (int) $member->id === (int) $boardMemberId)
            : null;

        $chairCommittees = $selectedMember
            ? $this->dashboard->chairCommitteesForBoardMember((int) $selectedMember->id)
            : collect();
        $filters = $this->resolvedFilters($request, $chairCommittees);

        return view('committee-reports.create', [
            'chairMembers' => $chairMembers,
            'selectionCommittees' => $selectionCommittees,
            'boardMemberId' => $selectedMember?->id,
            'q' => $filters['q'],
            'committeeId' => $filters['committee_id'],
            'chairCommittees' => $chairCommittees,
            'agendaItems' => $selectedMember
                ? $this->filteredAgendaItems((int) $selectedMember->id, $filters['q'], $filters['committee'])
                : collect(),
            'selectedAgendaIds' => old('agenda_item_ids', []),
            'targetSessions' => $this->reports->selectableTargetSessions(),
            'selectedSessionId' => old('legislative_session_id'),
            'agendaSearchUrl' => route('committee-reports.agendas'),
        ]);
    }

    public function edit(Request $request, BoardMemberCommitteeReport $committeeReport): View
    {
        $this->authorize('update', $committeeReport);
        abort_unless($request->user()?->hasModuleCapability(\App\Support\UserCapability::COMMITTEE_REPORTS), 403);

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
            'targetSessions' => $this->reports->selectableTargetSessions(),
            'selectedSessionId' => old('legislative_session_id', $committeeReport->legislative_session_id),
            'agendaSearchUrl' => route('committee-reports.agendas', [
                'report_id' => $committeeReport->id,
                'board_member_id' => $committeeReport->board_member_id,
            ]),
        ]);
    }

    public function agendas(Request $request): JsonResponse
    {
        $this->authorize('create', BoardMemberCommitteeReport::class);
        abort_unless($request->user()?->hasModuleCapability(\App\Support\UserCapability::COMMITTEE_REPORTS), 403);

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
        abort_unless($request->user()?->hasModuleCapability(\App\Support\UserCapability::COMMITTEE_REPORTS), 403);

        $validated = $request->validate([
            'board_member_id' => ['required', 'integer', 'exists:board_members,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'agenda_item_ids' => ['nullable', 'array'],
            'agenda_item_ids.*' => ['integer', 'exists:agenda_items,id'],
            'legislative_session_id' => ['nullable', 'integer', 'exists:legislative_sessions,id'],
        ]);

        $this->reports->store(
            $request->user(),
            $validated['pdf'],
            $validated['title'] ?? null,
            $validated['agenda_item_ids'] ?? [],
            (int) $validated['board_member_id'],
            legislativeSessionId: isset($validated['legislative_session_id'])
                ? (int) $validated['legislative_session_id']
                : null,
        );

        return redirect()
            ->route('committee-reports.index')
            ->with('status', 'Committee Report submitted. Tagged Agenda items and related Session folders were updated.');
    }

    public function update(Request $request, BoardMemberCommitteeReport $committeeReport): RedirectResponse
    {
        $this->authorize('update', $committeeReport);
        abort_unless($request->user()?->hasModuleCapability(\App\Support\UserCapability::COMMITTEE_REPORTS), 403);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'agenda_item_ids' => ['nullable', 'array'],
            'agenda_item_ids.*' => ['integer', 'exists:agenda_items,id'],
            'legislative_session_id' => ['nullable', 'integer', 'exists:legislative_sessions,id'],
        ]);

        $this->reports->update(
            $request->user(),
            $committeeReport,
            $validated['pdf'] ?? null,
            $validated['title'] ?? null,
            $validated['agenda_item_ids'] ?? [],
            legislativeSessionId: array_key_exists('legislative_session_id', $validated)
                ? (isset($validated['legislative_session_id']) ? (int) $validated['legislative_session_id'] : null)
                : null,
            updateSessionTarget: true,
        );

        return redirect()
            ->route('committee-reports.index')
            ->with('status', 'Committee report updated.');
    }

    public function destroy(Request $request, BoardMemberCommitteeReport $committeeReport): RedirectResponse
    {
        $this->authorize('delete', $committeeReport);
        abort_unless($request->user()?->hasModuleCapability(\App\Support\UserCapability::COMMITTEE_REPORTS), 403);

        $this->reports->delete($request->user(), $committeeReport);

        return redirect()
            ->route('committee-reports.index')
            ->with('status', 'Committee report deleted.');
    }

    public function pdf(Request $request, BoardMemberCommitteeReport $committeeReport): StreamedResponse
    {
        $this->authorize('view', $committeeReport);
        abort_unless(
            $request->user()?->hasModuleCapability(\App\Support\UserCapability::COMMITTEE_REPORTS)
                || $request->user()?->isBoardMember(),
            403
        );

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
     * Active committees that have a chair in the current term (for staff create picker).
     *
     * @return Collection<int, array{id: int, name: string, board_member_id: int}>
     */
    protected function chairCommitteesForSelection(): Collection
    {
        $term = $this->dashboard->resolveTerm();
        $activeChairIds = BoardMember::query()
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if ($activeChairIds === []) {
            return collect();
        }

        return CommitteeMembership::query()
            ->where('committee_term_id', $term->id)
            ->where('role', CommitteeMembershipRole::Chair)
            ->whereIn('board_member_id', $activeChairIds)
            ->with(['committee:id,name,is_active,sort_order'])
            ->get()
            ->filter(fn (CommitteeMembership $membership) => $membership->committee?->is_active)
            ->sortBy(fn (CommitteeMembership $membership) => [
                (int) ($membership->committee?->sort_order ?? 0),
                (string) ($membership->committee?->name ?? ''),
            ])
            ->values()
            ->map(fn (CommitteeMembership $membership) => [
                'id' => (int) $membership->committee_id,
                'name' => (string) $membership->committee?->name,
                'board_member_id' => (int) $membership->board_member_id,
            ]);
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
            CommitteeLookup::applyAgendaCommitteeFilter($agendaQuery, $committee);
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
