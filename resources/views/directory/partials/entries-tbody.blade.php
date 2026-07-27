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
                        <a href="mailto:{{ $email }}" class="splis-link">{{ $email }}</a>
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
                                        <a href="mailto:{{ $email }}" class="splis-link text-xs">{{ $email }}</a>
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
            <div class="inline-flex items-center gap-2">
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
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7" class="py-10 text-center text-slate-500">
            {{ ! empty($emptyMessage) ? $emptyMessage : 'No directory entries yet.' }}
        </td>
    </tr>
@endforelse
