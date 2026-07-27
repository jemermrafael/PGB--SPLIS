@extends('layouts.app')

@section('title', 'Staff Directory — '.config('app.name'))

@section('content')
<div class="w-full">
    <div class="splis-page-header">
        <x-page-heading
            title="Directory"
            subtitle="Find contact information for Provincial Government Offices and Personnel."
            icon="notebook"
            page="directory"
        />
        <div class="flex flex-wrap gap-2">
            @can('create', App\Models\DirectoryEntry::class)
                <a href="{{ route('directory.categories.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
                    Manage Categories
                </a>
                <a href="{{ route('directory.create') }}" class="splis-btn-primary inline-flex items-center gap-2">
                    <x-icon name="plus" class="h-4 w-4" stroke-width="2" />
                    Add Entry
                </a>
            @endcan
        </div>
    </div>

    @if ($categories->isNotEmpty())
        <div class="mb-4 flex flex-wrap gap-2">
            <a
                href="{{ route('directory.index') }}"
                @class([
                    'splis-btn-secondary text-sm',
                    'ring-2 ring-brand-200' => ! $selectedCategoryId,
                ])
            >All</a>
            @foreach ($categories as $category)
                <a
                    href="{{ route('directory.index', ['category' => $category->id]) }}"
                    @class([
                        'splis-btn-secondary text-sm',
                        'ring-2 ring-brand-200' => (int) $selectedCategoryId === (int) $category->id,
                    ])
                >{{ $category->name }}</a>
            @endforeach
        </div>
    @endif

    <div class="splis-table-wrap">
        <table class="splis-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Contact Number</th>
                    <th>Email</th>
                    <th>Focal persons</th>
                    <th>Designation</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    @php
                        $emails = $entry->emailList();
                        $focalPersons = $entry->isProvincialBoardCategory() ? $entry->focalPersonsList() : [];
                    @endphp
                    <tr>
                        <td class="font-medium text-slate-900 dark:text-slate-100">{{ $entry->name }}</td>
                        <td>{{ $entry->category?->name ?: '—' }}</td>
                        <td>{{ $entry->contact_number ?: '—' }}</td>
                        <td>
                            @if ($emails !== [])
                                <div class="flex flex-col gap-0.5">
                                    @foreach ($emails as $email)
                                        <a href="mailto:{{ $email }}" class="splis-link break-all">{{ $email }}</a>
                                    @endforeach
                                </div>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($focalPersons !== [])
                                <div class="space-y-1.5">
                                    @foreach ($focalPersons as $person)
                                        <div>
                                            <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $person['name'] !== '' ? $person['name'] : '—' }}</div>
                                            @if (($person['emails'] ?? []) !== [])
                                                <div class="flex flex-col gap-0.5">
                                                    @foreach ($person['emails'] as $email)
                                                        <a href="mailto:{{ $email }}" class="splis-link break-all text-xs">{{ $email }}</a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $entry->designation ?: '—' }}</td>
                        <td class="text-right">
                            @can('update', $entry)
                                <a href="{{ route('directory.edit', $entry) }}" class="splis-btn-secondary text-sm">Edit</a>
                            @endcan
                            @can('delete', $entry)
                                <form method="POST" action="{{ route('directory.destroy', $entry) }}" class="inline" onsubmit="return confirm('Remove this directory entry?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="splis-btn-ghost text-sm text-red-600">Delete</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-10 text-center text-slate-500">No directory entries yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($entries->hasPages())
        <div class="mt-4">{{ $entries->links() }}</div>
    @endif
</div>
@endsection
