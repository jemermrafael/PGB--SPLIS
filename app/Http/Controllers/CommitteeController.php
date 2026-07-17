<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\CommitteeTerm;
use App\Services\BoardMemberRosterService;
use App\Services\CommitteeRosterService;
use App\Support\TrashActivity;
use App\Support\CommitteeSecretaryOptions;
use App\Support\CommitteeTermSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommitteeController extends Controller
{
    public function __construct(
        protected CommitteeRosterService $rosterService,
        protected BoardMemberRosterService $boardMemberRosterService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Committee::class);

        ['terms' => $terms, 'selectedTerm' => $selectedTerm] = CommitteeTermSelection::resolve(
            $request->integer('term') ?: null,
        );

        $committeesQuery = Committee::query()->ordered();

        if (! $selectedTerm->is_current) {
            $committeesQuery->withRosterForTerm($selectedTerm->id);
        }

        $committees = $committeesQuery
            ->paginate(50)
            ->appends(['term' => $selectedTerm->id]);

        return view('committees.index', [
            'terms' => $terms,
            'selectedTerm' => $selectedTerm,
            'committees' => $committees,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Committee::class);

        $term = CommitteeTerm::currentOrCreate();

        return view('committees.form', [
            'committee' => new Committee([
                'is_active' => true,
                'sort_order' => (int) Committee::query()->max('sort_order') + 1,
            ]),
            'term' => $term,
            'terms' => CommitteeTerm::query()->ordered()->get(),
            'boardMembers' => $this->boardMemberRosterService->activeMembersForTermQuery($term)->get(),
            'secretaryOptions' => CommitteeSecretaryOptions::names(),
            'secretaryName' => '',
            'roster' => [
                'chair_id' => null,
                'vice_chair_id' => null,
                'member_ids' => [],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Committee::class);

        $data = $this->validated($request);
        $committee = Committee::create($data);

        $term = CommitteeTerm::query()->findOrFail((int) $request->input('committee_term_id'));
        $this->rosterService->saveRoster($committee, $term, $this->rosterInput($request));

        return redirect()
            ->route('committees.show', ['committee' => $committee, 'term' => $term->id])
            ->with('status', 'Committee created.');
    }

    public function show(Request $request, Committee $committee): View|RedirectResponse
    {
        $this->authorize('view', $committee);

        $requestedTermId = $request->integer('term') ?: null;

        $terms = CommitteeTerm::query()
            ->whereHas('memberships', fn ($query) => $query->where('committee_id', $committee->id))
            ->ordered()
            ->get();

        if ($terms->isEmpty()) {
            ['terms' => $terms, 'selectedTerm' => $selectedTerm] = CommitteeTermSelection::resolve($requestedTermId);
        } else {
            ['selectedTerm' => $resolved] = CommitteeTermSelection::resolve($requestedTermId);

            $selectedTerm = $terms->firstWhere('id', $resolved->id)
                ?? $terms->firstWhere('is_current', true)
                ?? $terms->first();

            if ($requestedTermId && $selectedTerm->id !== $requestedTermId) {
                return redirect()->route('committees.show', [
                    'committee' => $committee,
                    'term' => $selectedTerm->id,
                ]);
            }
        }

        $memberships = $committee->memberships()
            ->where('committee_term_id', $selectedTerm->id)
            ->with('boardMember')
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn ($membership) => $membership->role->value);

        return view('committees.show', [
            'committee' => $committee,
            'terms' => $terms,
            'selectedTerm' => $selectedTerm,
            'memberships' => $memberships,
            'previousCommittee' => $committee->trashed() ? null : $committee->previousInList(),
            'nextCommittee' => $committee->trashed() ? null : $committee->nextInList(),
        ]);
    }

    public function edit(Committee $committee): View
    {
        $this->authorize('update', $committee);

        $termId = (int) request('term', CommitteeTerm::query()->current()->value('id'));
        $term = CommitteeTerm::query()->find($termId) ?? CommitteeTerm::currentOrCreate();

        $roster = $this->rosterService->rosterForTerm($committee, $term);

        return view('committees.form', [
            'committee' => $committee,
            'term' => $term,
            'terms' => CommitteeTerm::query()->ordered()->get(),
            'boardMembers' => $this->boardMemberRosterService->activeMembersForTermQuery($term)->get(),
            'secretaryOptions' => CommitteeSecretaryOptions::names(),
            'secretaryName' => $roster['secretary'],
            'roster' => $roster,
        ]);
    }

    public function update(Request $request, Committee $committee): RedirectResponse
    {
        $this->authorize('update', $committee);

        $committee->update($this->validated($request, $committee));

        $term = CommitteeTerm::query()->findOrFail((int) $request->input('committee_term_id'));
        $this->rosterService->saveRoster($committee, $term, $this->rosterInput($request));

        return redirect()
            ->route('committees.show', ['committee' => $committee, 'term' => $term->id])
            ->with('status', 'Committee updated.');
    }

    public function destroy(Committee $committee): RedirectResponse
    {
        $this->authorize('delete', $committee);

        TrashActivity::record('committee.trashed', $committee);
        $committee->delete();

        return redirect()
            ->route(auth()->user()?->isSuperadmin() ? 'admin.trash.index' : 'committees.index', auth()->user()?->isSuperadmin() ? ['type' => 'committees'] : [])
            ->with('status', 'Committee moved to trash.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Committee $committee = null): array
    {
        return $request->validate([
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'name' => [
                'required',
                'string',
                'max:200',
                Rule::unique('committees', 'name')->ignore($committee?->id),
            ],
            'email' => ['nullable', 'email', 'max:200'],
            'is_active' => ['sometimes', 'boolean'],
            'committee_term_id' => ['required', 'integer', 'exists:committee_terms,id'],
            'chair_id' => ['nullable', 'integer', 'exists:board_members,id'],
            'vice_chair_id' => ['nullable', 'integer', 'exists:board_members,id'],
            'secretary' => ['nullable', 'string', 'max:200'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:board_members,id'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * @return array{
     *     chair_id?: int|null,
     *     vice_chair_id?: int|null,
     *     secretary?: string|null,
     *     member_ids?: list<int>
     * }
     */
    protected function rosterInput(Request $request): array
    {
        $secretary = trim((string) $request->input('secretary', ''));

        return [
            'chair_id' => $request->integer('chair_id') ?: null,
            'vice_chair_id' => $request->integer('vice_chair_id') ?: null,
            'secretary' => $secretary !== '' ? $secretary : null,
            'member_ids' => collect($request->input('member_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all(),
        ];
    }
}
