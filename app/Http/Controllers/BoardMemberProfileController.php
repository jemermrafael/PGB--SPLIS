<?php

namespace App\Http\Controllers;

use App\Models\BoardMember;
use App\Models\User;
use App\Services\BoardMemberDashboardService;
use App\Services\BoardMemberProfileService;
use App\Support\CommitteeTermSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class BoardMemberProfileController extends Controller
{
    public function edit(
        Request $request,
        BoardMemberProfileService $profiles,
        BoardMemberDashboardService $dashboard,
    ): View {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isBoardMember(), 403);

        $boardMember = $user->boardMember;
        $selectedTerm = CommitteeTermSelection::current();
        $profile = $boardMember
            ? $profiles->build($boardMember, $selectedTerm)
            : ['roles' => ['chair' => collect(), 'vice_chair' => collect(), 'member' => collect()], 'otherTerms' => collect()];

        return view('board-member.profile.edit', [
            'user' => $user,
            'boardMember' => $boardMember,
            'unlinked' => $boardMember === null,
            'selectedTerm' => $selectedTerm,
            'roles' => $profile['roles'],
            'assignmentCount' => $dashboard->committeeAssignmentsFor($user, $selectedTerm)->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isBoardMember(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'honorific' => ['nullable', 'string', 'max:40'],
        ]);

        $user->fill([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        if ($user->boardMember instanceof BoardMember) {
            $user->boardMember->forceFill([
                'honorific' => trim((string) ($data['honorific'] ?? '')) ?: null,
            ])->save();
        }

        return redirect()
            ->route('board-member.profile.edit')
            ->with('status', 'Your profile was updated.');
    }
}
