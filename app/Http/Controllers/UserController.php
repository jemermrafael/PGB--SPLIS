<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->orderBy('name')
            ->paginate(25);

        return view('users.index', [
            'users' => $users,
            'roles' => UserRole::assignable(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.form', [
            'user' => new User(['is_active' => true, 'role' => UserRole::Encoder]),
            'roles' => UserRole::assignable(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $this->validated($request);

        User::create($data);

        return redirect()
            ->route('users.index')
            ->with('status', 'User account created.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.form', [
            'user' => $user,
            'roles' => UserRole::assignable(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if ($user->id === $request->user()->id && ! $request->boolean('is_active')) {
            return back()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
        }

        if ($user->isSuperadmin()
            && $request->input('role') !== UserRole::Superadmin->value
            && User::query()->where('role', UserRole::Superadmin)->count() <= 1) {
            return back()->withErrors(['role' => 'At least one superadmin account must remain.']);
        }

        $data = $this->validated($request, $user);
        $user->update($data);

        return redirect()
            ->route('users.index')
            ->with('status', 'User account updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('status', 'User account deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?User $user = null): array
    {
        $isEdit = $user !== null;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user?->id),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => [
                $isEdit ? 'nullable' : 'required',
                'confirmed',
                Password::defaults(),
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
