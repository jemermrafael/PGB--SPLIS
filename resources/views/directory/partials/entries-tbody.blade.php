@php
    $canManage = $canManage ?? (auth()->user()?->can('create', App\Models\DirectoryEntry::class) ?? false);
    $allowReorder = $allowReorder ?? true;
    $page = method_exists($entries, 'currentPage') ? $entries->currentPage() : 1;
    $firstItem = method_exists($entries, 'firstItem') ? ($entries->firstItem() ?? 1) : 1;
    $total = method_exists($entries, 'total') ? $entries->total() : $entries->count();
@endphp
@forelse ($entries as $entry)
    @php
        $emails = $entry->emailList();
        $position = $firstItem + $loop->index;
        $isFirst = $position <= 1;
        $isLast = $position >= $total;
    @endphp
    <tr>
        @if ($canManage)
            <td data-list-edit-only>
                @can('delete', $entry)
                    <input
                        type="checkbox"
                        value="{{ $entry->id }}"
                        data-directory-checkbox
                        data-list-edit-checkbox
                        class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                        aria-label="Select {{ $entry->name }}"
                    >
                @endcan
            </td>
        @endif
        <td class="font-medium text-slate-900 dark:text-slate-100">{{ $entry->name }}</td>
        <td>{{ $entry->contact_number ?: '—' }}</td>
        <td>
            @if ($emails !== [])
                <div class="flex flex-col gap-0.5">
                    @foreach ($emails as $email)
                        <a href="mailto:{{ $email }}" class="splis-link">{{ $email }}</a>
                    @endforeach
                </div>
            @else
                —
            @endif
        </td>
        <td>{{ $entry->designation ?: '—' }}</td>
        <td class="text-right" data-list-edit-only>
            <div class="inline-flex items-center gap-2">
                @if ($allowReorder)
                    @can('update', $entry)
                        <div class="flex items-center gap-1">
                            <form method="POST" action="{{ route('directory.move', $entry) }}">
                                @csrf
                                <input type="hidden" name="direction" value="-1">
                                <input type="hidden" name="page" value="{{ $page }}">
                                <button
                                    type="submit"
                                    class="splis-btn-secondary px-2 py-1 text-sm"
                                    title="Move up"
                                    @disabled($isFirst)
                                >
                                    ↑
                                </button>
                            </form>
                            <form method="POST" action="{{ route('directory.move', $entry) }}">
                                @csrf
                                <input type="hidden" name="direction" value="1">
                                <input type="hidden" name="page" value="{{ $page }}">
                                <button
                                    type="submit"
                                    class="splis-btn-secondary px-2 py-1 text-sm"
                                    title="Move down"
                                    @disabled($isLast)
                                >
                                    ↓
                                </button>
                            </form>
                        </div>
                    @endcan
                @endif
                @can('update', $entry)
                    <a href="{{ route('directory.edit', $entry) }}" class="splis-btn-secondary text-sm">Edit</a>
                @endcan
                @can('delete', $entry)
                    <form
                        method="POST"
                        action="{{ route('directory.destroy', $entry) }}"
                        class="inline"
                        data-confirm-submit
                        data-confirm-title="Remove directory entry?"
                        data-confirm-message="Remove this directory entry? This cannot be undone."
                        data-confirm-label="Delete"
                    >
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
        <td colspan="{{ $canManage ? 6 : 5 }}" class="py-10 text-center text-slate-500">
            {{ ! empty($emptyMessage) ? $emptyMessage : 'No directory entries yet.' }}
        </td>
    </tr>
@endforelse
