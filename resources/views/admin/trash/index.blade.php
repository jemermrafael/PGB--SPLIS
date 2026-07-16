@extends('layouts.app')

@section('title', 'Trash — '.config('app.name'))

@section('content')
<div class="max-w-6xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Trash</h1>
            <p class="splis-page-subtitle">Soft-deleted records stay here until restored or permanently removed. Superadmin only.</p>
        </div>
    </div>

    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ($types as $key => $meta)
            <a
                href="{{ route('admin.trash.index', ['type' => $key]) }}"
                @class([
                    'splis-btn-secondary !px-3 !py-1.5 text-sm',
                    'ring-2 ring-brand-600' => $type === $key,
                ])
            >
                {{ $meta['label'] }}
                @if (($counts[$key] ?? 0) > 0)
                    <span class="ml-1 tabular-nums opacity-70">({{ number_format($counts[$key]) }})</span>
                @endif
            </a>
        @endforeach
    </div>

    <div class="splis-table-wrap">
        <table class="splis-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="min-w-[14rem]">Details</th>
                    <th class="hidden md:table-cell">Deleted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="whitespace-nowrap font-medium">
                            @if ($row['open_url'])
                                <a href="{{ $row['open_url'] }}" class="splis-link">{{ $row['primary'] }}</a>
                            @else
                                {{ $row['primary'] }}
                            @endif
                        </td>
                        <td class="text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($row['secondary'], 120) }}</td>
                        <td class="hidden md:table-cell whitespace-nowrap">{{ $row['deleted_at'] }}</td>
                        <td class="text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                <form method="POST" action="{{ route('admin.trash.restore', ['type' => $type, 'id' => $row['id']]) }}">
                                    @csrf
                                    <button type="submit" class="splis-link text-sm">Restore</button>
                                </form>
                                <form
                                    method="POST"
                                    action="{{ route('admin.trash.force-destroy', ['type' => $type, 'id' => $row['id']]) }}"
                                    onsubmit="return confirm('Permanently delete this item? This cannot be undone.')"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-red-700 hover:underline dark:text-red-400">Delete forever</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-10 text-center text-slate-500">Trash is empty for this type.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="mt-4">{{ $items->links() }}</div>
    @endif
</div>
@endsection
