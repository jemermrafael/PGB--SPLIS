@extends('layouts.app')

@php
    $isEdit = $entry->exists;
    $emailValues = old('emails', $entry->emailList());
    if ($emailValues === []) {
        $emailValues = [''];
    }
@endphp

@section('title', ($isEdit ? 'Edit Directory Entry' : 'New Directory Entry').' — '.config('app.name'))

@section('content')
<div class="max-w-2xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ $isEdit ? 'Edit Directory Entry' : 'New Directory Entry' }}</h1>
        </div>
    </div>

    <form method="POST" action="{{ $isEdit ? route('directory.update', $entry) : route('directory.store') }}" class="splis-card splis-card-body space-y-5">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div>
            <label class="splis-label" for="name">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name', $entry->name) }}" required class="splis-input">
        </div>

        <div>
            <label class="splis-label" for="contact_number">Contact number</label>
            <input type="text" name="contact_number" id="contact_number" value="{{ old('contact_number', $entry->contact_number) }}" class="splis-input">
        </div>

        <div>
            <div class="mb-1 flex items-center justify-between gap-2">
                <label class="splis-label">Email addresses</label>
                <button type="button" class="text-xs splis-link" data-add-email>Add another email</button>
            </div>
            <div class="space-y-2" data-email-list>
                @foreach ($emailValues as $index => $email)
                    <input
                        type="email"
                        name="emails[]"
                        value="{{ $email }}"
                        class="splis-input"
                        placeholder="name@example.com"
                        @if ($index === 0) id="email" @endif
                    >
                @endforeach
            </div>
            @error('emails.*')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-slate-500">Add as many emails as needed.</p>
        </div>

        <div>
            <label class="splis-label" for="designation">Designation</label>
            <input type="text" name="designation" id="designation" value="{{ old('designation', $entry->designation) }}" class="splis-input">
        </div>

        <div>
            <label class="splis-label" for="sort_order">Sort order</label>
            <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $entry->sort_order ?? 0) }}" min="0" class="splis-input w-32">
        </div>

        <div class="splis-form-actions">
            <button type="submit" class="splis-btn-primary">Save</button>
            <a href="{{ route('directory.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Cancel
            </a>
        </div>
    </form>
</div>
@push('scripts')
<script>
    (() => {
        const list = document.querySelector('[data-email-list]');
        const addBtn = document.querySelector('[data-add-email]');
        if (list && addBtn) {
            addBtn.addEventListener('click', () => {
                const input = document.createElement('input');
                input.type = 'email';
                input.name = 'emails[]';
                input.className = 'splis-input';
                input.placeholder = 'name@example.com';
                list.appendChild(input);
                input.focus();
            });
        }
    })();
</script>
@endpush
@endsection
