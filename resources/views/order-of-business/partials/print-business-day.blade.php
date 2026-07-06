<table class="ob-print-table ob-print-table--calendar">
    <tbody>
        @foreach ($segment['rows'] as $row)
            @if (($row['kind'] ?? '') === 'subsection')
                <tr>
                    <td colspan="2" class="ob-print-subsection">{{ $row['text'] ?? '' }}</td>
                </tr>
            @elseif (($row['kind'] ?? '') === 'agenda')
                <tr>
                    <td class="ob-print-unfinished-meta">
                        @include('order-of-business.partials.print-agenda-meta', ['row' => $row['row'] ?? []])
                    </td>
                    <td class="ob-print-unfinished-title">
                        @include('order-of-business.partials.print-agenda-title', ['row' => $row['row'] ?? []])
                    </td>
                </tr>
            @elseif (($row['kind'] ?? '') === 'none')
                <tr>
                    <td class="ob-print-unfinished-meta">
                        @include('order-of-business.partials.print-agenda-meta', ['row' => [], 'empty' => true])
                    </td>
                    <td class="ob-print-unfinished-title">None</td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>
