@extends('layouts.app')

@section('title', 'Resolution Trash — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Resolution Trash</h1>
            <p class="splis-page-subtitle">Deleted resolutions are kept here until permanently removed. Restore or delete forever.</p>
        </div>
        <a href="{{ route('resolutions.index') }}" class="splis-btn-secondary">Back to resolutions</a>
    </div>

    <div class="splis-table-wrap">
        <table class="splis-table">
            <thead>
                <tr>
                    <th>Resolution No.</th>
                    <th class="min-w-[14rem]">Title</th>
                    <th class="hidden md:table-cell">Deleted</th>
                    <th class="hidden lg:table-cell">Deleted by</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($resolutions as $resolution)
                    <tr>
                        <td class="whitespace-nowrap">{{ $resolution->series }}-{{ $resolution->resolution_no }}</td>
                        <td>
                            <a href="{{ route('resolutions.show', $resolution) }}" class="splis-table-title splis-table-title--list">
                                {{ $resolution->resolution_title }}
                            </a>
                        </td>
                        <td class="hidden md:table-cell whitespace-nowrap">{{ $resolution->deleted_at?->format('M d, Y g:i A') ?: '—' }}</td>
                        <td class="hidden lg:table-cell">{{ $resolution->creator?->name ?: '—' }}</td>
                        <td class="text-right">
                            <a href="{{ route('resolutions.show', $resolution) }}" class="splis-link text-sm">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-10 text-center text-slate-500">Trash is empty.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($resolutions->hasPages())
        <div class="mt-4">{{ $resolutions->links() }}</div>
    @endif
</div>
@endsection
