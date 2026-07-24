@extends('layouts.app')

@section('title', 'Staff Directory — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header">
        <x-page-heading
            title="Directory"
            subtitle="Find contact information for Provincial Government Offices and Personnel."
            icon="notebook"
        />
        @can('create', App\Models\DirectoryEntry::class)
            <a href="{{ route('directory.create') }}" class="splis-btn-primary inline-flex items-center gap-2">
                <x-icon name="plus" class="h-4 w-4" stroke-width="2" />
                Add Entry
            </a>
        @endcan
    </div>

    <div class="splis-table-wrap">
        <table class="splis-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Contact Number</th>
                    <th>Email</th>
                    <th>Designation</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="font-medium text-slate-900 dark:text-slate-100">{{ $entry->name }}</td>
                        <td>{{ $entry->contact_number ?: '—' }}</td>
                        <td>
                            @if ($entry->email)
                                <a href="mailto:{{ $entry->email }}" class="splis-link">{{ $entry->email }}</a>
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
                        <td colspan="5" class="py-10 text-center text-slate-500">No directory entries yet.</td>
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
