<?php

namespace App\Http\Controllers;

use App\Models\BoardMember;
use App\Models\BoardMemberTerm;
use App\Models\CommitteeTerm;
use App\Services\BoardMemberProfileService;
use App\Services\BoardMemberRosterService;
use App\Support\CommitteeTermSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BoardMemberController extends Controller
{
    public function __construct(
        protected BoardMemberProfileService $profileService,
        protected BoardMemberRosterService $rosterService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BoardMember::class);

        ['terms' => $terms, 'selectedTerm' => $selectedTerm] = CommitteeTermSelection::resolve(
            $request->integer('term') ?: null,
        );

        $boardMembersByDistrict = $this->rosterService->rosterGroupedByDistrict($selectedTerm);

        return view('board-members.index', [
            'terms' => $terms,
            'selectedTerm' => $selectedTerm,
            'boardMembersByDistrict' => $boardMembersByDistrict,
        ]);
    }

    public function show(Request $request, BoardMember $boardMember): View
    {
        $this->authorize('view', $boardMember);

        ['terms' => $terms, 'selectedTerm' => $selectedTerm] = CommitteeTermSelection::resolve(
            $request->integer('term') ?: null,
        );

        $assignment = $this->rosterService->assignmentFor($boardMember, $selectedTerm);
        $profile = $this->profileService->build($boardMember, $selectedTerm);

        return view('board-members.show', [
            'boardMember' => $boardMember,
            'terms' => $terms,
            'selectedTerm' => $selectedTerm,
            'assignment' => $assignment,
            'roles' => $profile['roles'],
            'otherTerms' => $profile['otherTerms'],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', BoardMember::class);

        ['terms' => $terms, 'selectedTerm' => $selectedTerm] = CommitteeTermSelection::resolve(
            $request->integer('term') ?: null,
        );

        return view('board-members.form', [
            'boardMember' => new BoardMember(['is_active' => true]),
            'terms' => $terms,
            'term' => $selectedTerm,
            'assignment' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', BoardMember::class);

        $data = $this->validated($request);
        $term = CommitteeTerm::query()->findOrFail((int) $request->input('committee_term_id'));

        $boardMember = BoardMember::create([
            'name' => $data['name'],
            'honorific' => $data['honorific'],
            'district' => $term->is_current ? $data['district'] : null,
            'is_active' => $term->is_current ? $data['is_active'] : true,
        ]);

        $this->rosterService->saveAssignment($boardMember, $term, [
            'district' => $data['district'],
            'is_active' => $data['is_active'],
        ]);

        return redirect()
            ->route('board-members.index', ['term' => $term->id])
            ->with('status', 'Board member created.');
    }

    public function edit(Request $request, BoardMember $boardMember): View
    {
        $this->authorize('update', $boardMember);

        ['terms' => $terms, 'selectedTerm' => $selectedTerm] = CommitteeTermSelection::resolve(
            $request->integer('term') ?: null,
        );

        $assignment = $this->rosterService->assignmentFor($boardMember, $selectedTerm);

        return view('board-members.form', [
            'boardMember' => $boardMember,
            'terms' => $terms,
            'term' => $selectedTerm,
            'assignment' => $assignment,
        ]);
    }

    public function update(Request $request, BoardMember $boardMember): RedirectResponse
    {
        $this->authorize('update', $boardMember);

        $data = $this->validated($request, $boardMember);
        $term = CommitteeTerm::query()->findOrFail((int) $request->input('committee_term_id'));

        $boardMember->update([
            'name' => $data['name'],
            'honorific' => $data['honorific'],
        ]);

        $this->rosterService->saveAssignment($boardMember, $term, [
            'district' => $data['district'],
            'is_active' => $data['is_active'],
        ]);

        return redirect()
            ->route('board-members.index', ['term' => $term->id])
            ->with('status', 'Board member updated.');
    }

    public function destroy(BoardMember $boardMember): RedirectResponse
    {
        $this->authorize('delete', $boardMember);

        $boardMember->delete();

        return redirect()
            ->route('board-members.index')
            ->with('status', 'Board member deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?BoardMember $boardMember = null): array
    {
        $termId = (int) $request->input('committee_term_id');
        $districts = config('board_members.districts', []);
        $singleSeatDistricts = array_values(array_diff($districts, ['Ex Officio']));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'honorific' => ['nullable', 'string', 'max:50'],
            'committee_term_id' => ['required', 'integer', 'exists:committee_terms,id'],
            'district' => ['nullable', 'string', Rule::in($districts)],
            'is_active' => ['sometimes', 'boolean'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
        ];

        $district = $data['district'] ?? null;

        if ($district !== null && $district !== '' && in_array($district, $singleSeatDistricts, true)) {
            $conflict = BoardMemberTerm::query()
                ->where('committee_term_id', $termId)
                ->where('district', $district)
                ->when($boardMember !== null, fn ($query) => $query->where('board_member_id', '!=', $boardMember->id))
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'district' => "Another board member is already assigned to {$district} for this term.",
                ]);
            }
        }

        return $data;
    }
}
