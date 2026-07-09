@if (! empty($rows))
    <ul class="splis-analytics-bars space-y-3">
        @foreach ($rows as $row)
            <li>
                <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                    @if (! empty($row['url']))
                        <a href="{{ $row['url'] }}" class="splis-link font-medium">{{ $row['label'] }}</a>
                    @else
                        <span class="font-medium text-slate-800 dark:text-slate-100">{{ $row['label'] }}</span>
                    @endif
                    <span class="shrink-0 tabular-nums text-slate-500 dark:text-slate-400">
                        {{ number_format((int) ($row['value'] ?? 0)) }}
                        @if (! empty($row['meta']))
                            <span class="text-xs">· {{ $row['meta'] }}</span>
                        @endif
                    </span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    <div
                        class="splis-analytics-bar-fill h-2 rounded-full {{ $row['tone'] ?? 'bg-brand-600' }}"
                        style="width: {{ max((int) ($row['percent'] ?? 0), 0) }}%"
                    ></div>
                </div>
            </li>
        @endforeach
    </ul>
@else
    <p class="py-6 text-center text-sm text-slate-500">No data for this chart yet.</p>
@endif
