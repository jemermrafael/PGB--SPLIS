@extends('layouts.app')

@php
    $isEdit = $user->exists;
@endphp

@section('title', ($isEdit ? 'Edit User' : 'New User').' — '.config('app.name'))

@section('content')
<div class="max-w-2xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ $isEdit ? 'Edit user' : 'New user' }}</h1>
            <p class="splis-page-subtitle">{{ $isEdit ? 'Update account details and permissions.' : 'Create a new SPLIS account.' }}</p>
        </div>
    </div>

    <form method="POST" action="{{ $isEdit ? route('users.update', $user) : route('users.store') }}" class="splis-card splis-card-body space-y-5">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div>
            <label class="splis-label" for="name">Full name</label>
            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required class="splis-input">
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="splis-label" for="username">Username</label>
                <input type="text" name="username" id="username" value="{{ old('username', $user->username) }}" required class="splis-input" autocomplete="off">
            </div>
            <div>
                <label class="splis-label" for="email">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required class="splis-input">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="splis-label" for="password">Password{{ $isEdit ? ' (leave blank to keep)' : '' }}</label>
                <input type="password" name="password" id="password" class="splis-input" {{ $isEdit ? '' : 'required' }} autocomplete="new-password">
            </div>
            <div>
                <label class="splis-label" for="password_confirmation">Confirm password</label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="splis-input" autocomplete="new-password">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="splis-label" for="role">Role</label>
                <select name="role" id="role" class="splis-select" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <label class="flex w-full items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    Active account
                </label>
            </div>
        </div>

        <div id="board-member-link-wrap" @class(['hidden' => old('role', $user->role?->value) !== 'board_member'])>
            <label class="splis-label" for="board_member_id">Linked board member</label>
            <select name="board_member_id" id="board_member_id" class="splis-select">
                <option value="">Select board member</option>
                @foreach ($boardMembers as $member)
                    <option value="{{ $member->id }}" @selected((string) old('board_member_id', $user->board_member_id) === (string) $member->id)>{{ $member->displayName() }}{{ $member->district ? ' · '.$member->district : '' }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Required for Board Member accounts — links the login to the legislative roster.</p>
        </div>

        <script>
            document.getElementById('role')?.addEventListener('change', (event) => {
                const wrap = document.getElementById('board-member-link-wrap');
                if (!wrap) return;
                wrap.classList.toggle('hidden', event.target.value !== 'board_member');
            });
        </script>

        <div class="flex gap-2 pt-2">
            <button type="submit" class="splis-btn-primary">Save user</button>
            <a href="{{ route('users.index') }}" class="splis-btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
