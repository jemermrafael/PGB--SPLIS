<?php

namespace App\Http\Controllers;

use App\Models\BoardMember;
use App\Services\BoardMemberProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BoardMemberController extends Controller
{
    public function __construct(
        protected BoardMemberProfileService $profileService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', BoardMember::class);

        $districtOrder = config('board_members.districts', []);
        $allMembers = BoardMember::query()
            ->whereIn('district', $districtOrder)
            ->ordered()
            ->get();

        $boardMembersByDistrict = collect($districtOrder)
            ->mapWithKeys(fn (string $district) => [
                $district => $allMembers->where('district', $district)->values(),
            ])
            ->filter(fn ($members) => $members->isNotEmpty());

        return view('board-members.index', [
            'boardMembersByDistrict' => $boardMembersByDistrict,
        ]);
    }

    public function show(BoardMember $boardMember): View
    {
        $this->authorize('view', $boardMember);

        $profile = $this->profileService->build($boardMember);

        return view('board-members.show', [
            'boardMember' => $boardMember,
            'currentTerm' => $profile['currentTerm'],
            'currentRoles' => $profile['current'],
            'history' => $profile['history'],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', BoardMember::class);

        return view('board-members.form', [
            'boardMember' => new BoardMember(['is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', BoardMember::class);

        BoardMember::create($this->validated($request));

        return redirect()
            ->route('board-members.index')
            ->with('status', 'Board member created.');
    }

    public function edit(BoardMember $boardMember): View
    {
        $this->authorize('update', $boardMember);

        return view('board-members.form', [
            'boardMember' => $boardMember,
        ]);
    }

    public function update(Request $request, BoardMember $boardMember): RedirectResponse
    {
        $this->authorize('update', $boardMember);

        $boardMember->update($this->validated($request));

        return redirect()
            ->route('board-members.index')
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
    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'honorific' => ['nullable', 'string', 'max:50'],
            'district' => ['nullable', 'string', Rule::in(config('board_members.districts', []))],
            'is_active' => ['sometimes', 'boolean'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
