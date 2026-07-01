@extends('layouts.app')

@section('title', 'Users — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">User management</h1>
            <p class="splis-page-subtitle">Create and manage SPLIS accounts. Superadmin only.</p>
        </div>
        @can('create', App\Models\User::class)
            <a href="{{ route('users.create') }}" class="splis-btn-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Add user
            </a>
        @endcan
    </div>

    <div class="splis-table-wrap">
        <table class="splis-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th class="hidden md:table-cell">Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td class="font-medium text-slate-900 dark:text-slate-100">{{ $user->name }}</td>
                        <td class="whitespace-nowrap">{{ $user->username }}</td>
                        <td class="hidden md:table-cell">{{ $user->email }}</td>
                        <td>{{ $user->role->label() }}</td>
                        <td>
                            @if ($user->is_active)
                                <span class="splis-badge-linked">Active</span>
                            @else
                                <span class="splis-badge-unlinked">Inactive</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex justify-end gap-2">
                                @can('update', $user)
                                    <a href="{{ route('users.edit', $user) }}" class="splis-btn-secondary text-sm">Edit</a>
                                @endcan
                                @can('delete', $user)
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Delete this user account?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="splis-btn-ghost text-sm text-red-600">Delete</button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center text-slate-400">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>
</div>
@endsection
