<table class="ob-print-table ob-print-table--roman">
    <tbody>
        @foreach ($segment['rows'] as $row)
            <tr>
                <td class="ob-print-roman">{{ $row['numeral'] ?? '' }}</td>
                <td class="ob-print-roman-label">{{ $row['title'] ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
