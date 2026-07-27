@extends('layouts.app')

@php
    $isEdit = $entry->exists;
    $emailValues = old('emails', $entry->emailList());
    if ($emailValues === []) {
        $emailValues = [''];
    }
    $selectedCategoryId = (string) old('directory_category_id', $entry->directory_category_id);
    $provincialBoardCategoryIds = $categories
        ->filter(fn ($category) => $category->isProvincialBoard())
        ->pluck('id')
        ->map(fn ($id) => (string) $id)
        ->all();
    $showFocalPersons = in_array($selectedCategoryId, $provincialBoardCategoryIds, true);
    $focalPersons = old('focal_persons', $entry->focalPersonsList());
    if ($focalPersons === []) {
        $focalPersons = [
            ['name' => '', 'emails' => ['']],
            ['name' => '', 'emails' => ['']],
        ];
    }
    foreach ($focalPersons as $i => $person) {
        if (($person['emails'] ?? []) === []) {
            $focalPersons[$i]['emails'] = [''];
        }
    }
@endphp

@section('title', ($isEdit ? 'Edit Directory Entry' : 'New Directory Entry').' — '.config('app.name'))

@section('content')
<div class="max-w-2xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ $isEdit ? 'Edit directory entry' : 'New Directory Entry' }}</h1>
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
            <div class="mb-1 flex items-center justify-between gap-2">
                <label class="splis-label" for="directory_category_id">Category</label>
                @can('create', App\Models\DirectoryEntry::class)
                    <a href="{{ route('directory.categories.index') }}" class="text-xs splis-link">Manage Categories</a>
                @endcan
            </div>
            <select
                name="directory_category_id"
                id="directory_category_id"
                class="splis-select"
                data-category-select
                data-provincial-board-ids="{{ implode(',', $provincialBoardCategoryIds) }}"
            >
                <option value="">— No category —</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($selectedCategoryId === (string) $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Focal persons appear when category is Provincial Board.</p>
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

        <div
            class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-800/50 {{ $showFocalPersons ? '' : 'hidden' }}"
            data-focal-persons
        >
            <div class="mb-3 flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Focal persons</h2>
                    <p class="mt-1 text-xs text-slate-500">For Provincial Board entries — add 2 or more focal persons with emails.</p>
                </div>
                <button type="button" class="splis-btn-secondary text-sm" data-add-focal-person>Add Focal Person</button>
            </div>

            <div class="space-y-4" data-focal-person-list>
                @foreach ($focalPersons as $index => $person)
                    <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/40" data-focal-person>
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <label class="splis-label mb-0">Focal person {{ $index + 1 }}</label>
                            <button type="button" class="text-xs text-red-600 dark:text-red-400" data-remove-focal-person>Remove</button>
                        </div>
                        <div class="mb-2">
                            <label class="splis-label" for="focal-name-{{ $index }}">Name</label>
                            <input
                                type="text"
                                name="focal_persons[{{ $index }}][name]"
                                id="focal-name-{{ $index }}"
                                value="{{ $person['name'] ?? '' }}"
                                class="splis-input"
                                placeholder="Full name"
                            >
                        </div>
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <label class="splis-label mb-0">Emails</label>
                                <button type="button" class="text-xs splis-link" data-add-focal-email>Add email</button>
                            </div>
                            <div class="space-y-2" data-focal-email-list>
                                @foreach (($person['emails'] ?? ['']) as $email)
                                    <input
                                        type="email"
                                        name="focal_persons[{{ $index }}][emails][]"
                                        value="{{ $email }}"
                                        class="splis-input"
                                        placeholder="name@example.com"
                                    >
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @error('focal_persons.*.emails.*')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="splis-label" for="designation">Designation</label>
            <input type="text" name="designation" id="designation" value="{{ old('designation', $entry->designation) }}" class="splis-input">
        </div>

        <div>
            <label class="splis-label" for="sort_order">Sort order</label>
            <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $entry->sort_order ?? 0) }}" min="0" class="splis-input w-32">
        </div>

        <div class="flex gap-2 pt-2">
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

        const categorySelect = document.querySelector('[data-category-select]');
        const focalRoot = document.querySelector('[data-focal-persons]');
        if (categorySelect && focalRoot) {
            const provincialIds = (categorySelect.getAttribute('data-provincial-board-ids') || '')
                .split(',')
                .filter(Boolean);
            const syncFocal = () => {
                focalRoot.classList.toggle('hidden', !provincialIds.includes(categorySelect.value));
            };
            categorySelect.addEventListener('change', syncFocal);
            syncFocal();
        }

        const root = document.querySelector('[data-focal-persons]');
        if (!root) return;

        const personList = root.querySelector('[data-focal-person-list]');
        const addPersonBtn = root.querySelector('[data-add-focal-person]');

        const reindex = () => {
            personList.querySelectorAll('[data-focal-person]').forEach((block, index) => {
                const label = block.querySelector('.splis-label');
                if (label && label.textContent.startsWith('Focal person')) {
                    label.textContent = `Focal person ${index + 1}`;
                }
                const nameInput = block.querySelector('input[type="text"]');
                if (nameInput) {
                    nameInput.name = `focal_persons[${index}][name]`;
                    nameInput.id = `focal-name-${index}`;
                }
                block.querySelectorAll('input[type="email"]').forEach((emailInput) => {
                    emailInput.name = `focal_persons[${index}][emails][]`;
                });
            });
        };

        const personTemplate = () => {
            const index = personList.querySelectorAll('[data-focal-person]').length;
            const div = document.createElement('div');
            div.className = 'rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/40';
            div.setAttribute('data-focal-person', '');
            div.innerHTML = `
                <div class="mb-2 flex items-center justify-between gap-2">
                    <label class="splis-label mb-0">Focal person ${index + 1}</label>
                    <button type="button" class="text-xs text-red-600 dark:text-red-400" data-remove-focal-person>Remove</button>
                </div>
                <div class="mb-2">
                    <label class="splis-label" for="focal-name-${index}">Name</label>
                    <input type="text" name="focal_persons[${index}][name]" id="focal-name-${index}" class="splis-input" placeholder="Full name">
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <label class="splis-label mb-0">Emails</label>
                        <button type="button" class="text-xs splis-link" data-add-focal-email>Add email</button>
                    </div>
                    <div class="space-y-2" data-focal-email-list>
                        <input type="email" name="focal_persons[${index}][emails][]" class="splis-input" placeholder="name@example.com">
                    </div>
                </div>
            `;
            return div;
        };

        addPersonBtn?.addEventListener('click', () => {
            personList.appendChild(personTemplate());
            reindex();
        });

        root.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;

            if (target.matches('[data-add-focal-email]')) {
                const block = target.closest('[data-focal-person]');
                const emailList = block?.querySelector('[data-focal-email-list]');
                if (!emailList) return;
                const index = [...personList.querySelectorAll('[data-focal-person]')].indexOf(block);
                const input = document.createElement('input');
                input.type = 'email';
                input.name = `focal_persons[${index}][emails][]`;
                input.className = 'splis-input';
                input.placeholder = 'name@example.com';
                emailList.appendChild(input);
                input.focus();
            }

            if (target.matches('[data-remove-focal-person]')) {
                const block = target.closest('[data-focal-person]');
                if (!block) return;
                if (personList.querySelectorAll('[data-focal-person]').length <= 1) {
                    block.querySelectorAll('input').forEach((input) => { input.value = ''; });
                    return;
                }
                block.remove();
                reindex();
            }
        });
    })();
</script>
@endpush
@endsection
