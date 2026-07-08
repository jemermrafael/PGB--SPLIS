@php
    $isPaginator = $records instanceof \Illuminate\Pagination\LengthAwarePaginator;
    $rows = $isPaginator ? $records : collect($records);
    $colspan = ($showType ?? false) ? 7 : 6;
@endphp

<div id="bm-ordinances-table" class="splis-table-wrap splis-card overflow-hidden">
    <table class="splis-table">
        <thead>
            <tr>
                @if ($showType ?? false)
                    <th>Type</th>
                @endif
                <th>Number</th>
                <th>Title</th>
                <th class="hidden md:table-cell">Date received</th>
                <th class="hidden lg:table-cell">Date passed</th>
                <th class="hidden lg:table-cell">Date approved</th>
                <th class="hidden xl:table-cell">Board members</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $record)
                <tr>
                    @if ($showType ?? false)
                        <td class="whitespace-nowrap">
                            <span class="splis-badge">{{ $record['type_label'] }}</span>
                        </td>
                    @endif
                    <td class="whitespace-nowrap">
                        <a href="{{ $record['url'] }}" class="splis-link">{{ $record['number_label'] }} ({{ $record['series_year'] }})</a>
                    </td>
                    <td class="splis-table-title splis-table-title--list">
                        @php
                            $subject = trim((string) ($record['subject'] ?? ''));
                            $titleWords = $subject === '' ? [] : preg_split('/\s+/', $subject, -1, PREG_SPLIT_NO_EMPTY);
                            $titleTruncated = count($titleWords) > 20;
                            $titleDisplay = $titleTruncated
                                ? implode(' ', array_slice($titleWords, 0, 20)).'…'
                                : ($subject !== '' ? $subject : '—');
                        @endphp
                        @if ($titleTruncated)
                            <span class="splis-title-tip" data-full-title="{{ $subject }}" tabindex="0">{{ $titleDisplay }}</span>
                        @else
                            <span>{{ $titleDisplay }}</span>
                        @endif
                    </td>
                    <td class="hidden md:table-cell whitespace-nowrap">{{ $record['date_received']?->format('M j, Y') ?? '—' }}</td>
                    <td class="hidden lg:table-cell whitespace-nowrap">{{ $record['date_passed']?->format('M j, Y') ?? '—' }}</td>
                    <td class="hidden lg:table-cell whitespace-nowrap">{{ $record['date_approved']?->format('M j, Y') ?? '—' }}</td>
                    <td class="hidden xl:table-cell">{{ $record['authors'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" class="py-8 text-center text-sm text-slate-500">{{ $emptyMessage ?? 'No ordinances found.' }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if ($isPaginator && $records->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $records->links() }}</div>
    @endif
</div>
