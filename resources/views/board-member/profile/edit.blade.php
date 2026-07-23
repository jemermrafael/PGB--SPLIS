@extends('layouts.app')

@section('title', 'My Profile — '.config('app.name'))

@section('content')
<div class="max-w-3xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">My Profile</h1>
            <p class="splis-page-subtitle">Update your login details. Legislative roster data stays tied to your linked Board Member record.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="splis-btn-ghost inline-flex items-center gap-2">
            <x-icon name="layout-dashboard" class="h-4 w-4" />
            Dashboard
        </a>
    </div>

    @if ($unlinked)
        <div class="splis-alert-error mb-6">
            This login is not linked to a Board Member profile. Contact the SP administrator so your account stays connected to the roster.
        </div>
    @else
        <div class="splis-card splis-card-body mb-8 space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Linked Board Member</p>
            <div class="flex flex-wrap items-center gap-4">
                @if ($boardMember->photo_path)
                    <button
                        type="button"
                        class="splis-bm-photo-thumb"
                        style="width:130px;height:130px;padding:0;border:0;border-radius:0;overflow:hidden;flex-shrink:0;cursor:pointer;background:transparent"
                        data-pdf-modal-open
                        data-pdf-viewer="image"
                        data-pdf-src="{{ route('board-members.photo', $boardMember) }}"
                        data-pdf-url="{{ route('board-members.photo', $boardMember) }}"
                        data-pdf-title="{{ $boardMember->displayName() }}"
                        aria-label="View full profile photo"
                    >
                        <img
                            src="{{ route('board-members.photo', $boardMember) }}"
                            alt="Profile photo"
                            style="display:block;width:130px;height:130px;object-fit:contain;border-radius:0"
                        >
                    </button>
                @endif
                <div>
                    <p class="text-lg font-medium text-slate-900 dark:text-slate-100">{{ $boardMember->displayName() }}</p>
                    <p class="text-sm text-slate-500">
                        {{ $boardMember->districtForTerm($selectedTerm->id) ?: 'District not set' }}
                        · {{ $assignmentCount }} Committee Assignment(s) in {{ $selectedTerm->label }}
                    </p>
                    @if ($boardMember->mobile_number)
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Mobile: {{ $boardMember->mobile_number }}</p>
                    @endif
                    @if ($user->email)
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Email: {{ $user->email }}</p>
                    @endif
                </div>
            </div>
            <a href="{{ route('board-member.committees.index') }}" class="splis-link text-sm">View My Committees</a>
        </div>
    @endif

    <form method="POST" action="{{ route('board-member.profile.update') }}" enctype="multipart/form-data" class="splis-card splis-card-body space-y-5">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="splis-label" for="name">Display name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" class="splis-input" required>
            </div>
            <div>
                <label class="splis-label" for="username">Username</label>
                <input type="text" name="username" id="username" value="{{ old('username', $user->username) }}" class="splis-input" required autocomplete="username">
            </div>
            <div>
                <label class="splis-label" for="email">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" class="splis-input" required autocomplete="email">
            </div>
            @if (! $unlinked)
                <div class="sm:col-span-2">
                    <label class="splis-label" for="honorific">Honorific (roster)</label>
                    <input type="text" name="honorific" id="honorific" value="{{ old('honorific', $boardMember->honorific) }}" class="splis-input" placeholder="Hon." maxlength="40">
                    <p class="mt-1 text-xs text-slate-500">Shown with your official name on committee rosters. District and legal name are managed by the SP office.</p>
                </div>
                <div>
                    <label class="splis-label" for="mobile_number">Mobile number</label>
                    <input type="text" name="mobile_number" id="mobile_number" value="{{ old('mobile_number', $boardMember->mobile_number) }}" class="splis-input" maxlength="50">
                </div>
                <div>
                    <label class="splis-label" for="photo">Profile photo</label>
                    <input type="file" name="photo" id="photo" accept="image/*" class="splis-input">
                </div>
            @endif
            <div>
                <label class="splis-label" for="password">New password</label>
                <input type="password" name="password" id="password" class="splis-input" autocomplete="new-password">
            </div>
            <div>
                <label class="splis-label" for="password_confirmation">Confirm password</label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="splis-input" autocomplete="new-password">
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="splis-btn-primary">Save changes</button>
            <a href="{{ route('dashboard') }}" class="splis-btn-ghost inline-flex items-center gap-2">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
