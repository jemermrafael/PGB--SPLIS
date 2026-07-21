@foreach ($segments as $segment)
    @switch($segment['type'])
        @case('roman_section')
            <table class="ob-print-table ob-print-table--roman">
                <tbody>
                    <tr>
                        <td class="ob-print-roman">{{ \App\Support\ObRomanNumeral::display($segment['numeral'] ?? '') }}</td>
                        <td class="ob-print-roman-label">
                            @if (filled($segment['body_html'] ?? null))
                                {!! nl2br($segment['body_html']) !!}
                            @elseif (filled($segment['title'] ?? null))
                                {{ $segment['title'] }}
                            @else
                                {!! nl2br(e($segment['body'] ?? '')) !!}
                            @endif
                        </td>
                    </tr>
                    @if (blank($segment['body_html'] ?? null) && filled($segment['title'] ?? null) && filled($segment['body'] ?? null))
                        <tr>
                            <td></td>
                            <td>{!! nl2br(e($segment['body'])) !!}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
            @break

        @case('calendar_section')
            <table class="ob-print-table ob-print-table--roman">
                <tbody>
                    <tr>
                        <td class="ob-print-roman">{{ \App\Support\ObRomanNumeral::display($segment['numeral'] ?? '') }}</td>
                        <td class="ob-print-roman-label">{{ $segment['title'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="ob-print-subsection">{{ $segment['sub_label'] ?? '' }}</td>
                    </tr>
                </tbody>
            </table>
            @break

        @case('privilege_calendar_table')
            @include('order-of-business.partials.print-privilege-calendar', ['segment' => $segment])
            @break

        @case('business_day_table')
            @include('order-of-business.partials.print-business-day', ['segment' => $segment])
            @break

        @case('subsection')
            <table class="ob-print-table ob-print-table--section">
                <tbody>
                    <tr>
                        <td @class([
                            'ob-print-subsection',
                            'ob-print-subsection--major' => preg_match('/^[ABC]\.\s/u', trim($segment['text'] ?? '')),
                        ]) colspan="2">{{ $segment['text'] }}</td>
                    </tr>
                </tbody>
            </table>
            @break

        @case('paragraph')
            @if (filled($segment['text'] ?? null))
                <p class="ob-print-paragraph">{!! nl2br(e($segment['text'])) !!}</p>
            @endif
            @break

        @case('committee_reports_table')
            <table class="ob-print-table ob-print-table--committee">
                <tbody>
                    <tr>
                        <td class="ob-print-roman">{{ \App\Support\ObRomanNumeral::display('IV') }}</td>
                        <td colspan="2" class="ob-print-roman-label">COMMITTEE REPORT</td>
                    </tr>
                    <tr class="ob-print-table-head">
                        <th class="ob-print-col-no">No.</th>
                        <th class="ob-print-col-agenda">Agenda Item</th>
                        <th>Committee/s</th>
                    </tr>
                    @forelse ($segment['rows'] as $row)
                        <tr>
                            <td class="ob-print-col-no">
                                @if (filled($row['row_no'] ?? null))
                                    {{ $row['row_no'] }}.
                                @endif
                            </td>
                            <td class="ob-print-col-agenda">{!! \App\Support\ObAgendaSnapshot::displayAgendaNosLabelHtml($row) !!}</td>
                            <td>
                                <p class="ob-print-committee-name">{{ $row['committee_name'] ?? '' }}</p>
                                <p class="ob-print-chair">{{ \App\Support\ObCommitteeFormatter::chairedByLine($row['chair_name'] ?? '') }}</p>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="ob-print-empty">&nbsp;</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @break

        @case('unfinished_group')
            <table class="ob-print-table ob-print-table--unfinished">
                <tbody>
                    <tr>
                        <td colspan="2" class="ob-print-committee-header">
                            <p class="ob-print-committee-name">{{ $segment['committee_name'] ?: '—' }}</p>
                            <p class="ob-print-committee-chair">{{ \App\Support\ObCommitteeFormatter::chairLine($segment['chair_name'] ?? '') }}</p>
                        </td>
                    </tr>
                    @forelse ($segment['items'] as $item)
                        <tr>
                            <td class="ob-print-unfinished-meta">
                                @include('order-of-business.partials.print-agenda-meta', ['row' => $item])
                            </td>
                            <td class="ob-print-unfinished-title">
                                @include('order-of-business.partials.print-agenda-title', ['row' => $item])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="ob-print-empty">&nbsp;</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @break

        @case('reading_2nd_table')
        @case('reading_3rd_table')
        @case('unassigned_urgent_table')
            <table class="ob-print-table ob-print-table--agenda-items">
                <tbody>
                    @if (! empty($segment['none']))
                        <tr>
                            <td class="ob-print-unfinished-meta">
                                @include('order-of-business.partials.print-agenda-meta', ['row' => [], 'empty' => true])
                            </td>
                            <td class="ob-print-unfinished-title">None</td>
                        </tr>
                    @else
                        @forelse ($segment['rows'] as $row)
                            <tr>
                                <td class="ob-print-unfinished-meta">
                                    @include('order-of-business.partials.print-agenda-meta', ['row' => $row])
                                </td>
                                <td class="ob-print-unfinished-title">
                                    @include('order-of-business.partials.print-agenda-title', ['row' => $row])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="ob-print-unfinished-meta">
                                    @include('order-of-business.partials.print-agenda-meta', ['row' => [], 'empty' => true])
                                </td>
                                <td class="ob-print-unfinished-title">None</td>
                            </tr>
                        @endforelse
                    @endif
                </tbody>
            </table>
            @break

        @case('unassigned_regular_table')
            <table class="ob-print-table ob-print-table--unfinished">
                <tbody>
                    @if (filled($segment['subsection'] ?? null))
                        <tr>
                            <td colspan="2" class="ob-print-subsection">{{ $segment['subsection'] }}</td>
                        </tr>
                    @endif
                    @forelse ($segment['rows'] as $row)
                        <tr>
                            <td class="ob-print-unfinished-meta">
                                @include('order-of-business.partials.print-agenda-meta', ['row' => $row])
                            </td>
                            <td class="ob-print-unfinished-title">
                                @include('order-of-business.partials.print-agenda-title', ['row' => $row])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="ob-print-empty">&nbsp;</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @break

        @case('announcements_closing')
            <table class="ob-print-table ob-print-table--roman ob-print-table--announcements">
                <tbody>
                    <tr>
                        <td class="ob-print-roman">{{ \App\Support\ObRomanNumeral::display('VII') }}</td>
                        <td class="ob-print-roman-label">ANNOUNCEMENTS/INFORMATION/CORRESPONDENCE</td>
                    </tr>
                    @forelse ($segment['rows'] as $row)
                        <tr>
                            <td class="ob-print-announce-col">{!! nl2br(e($row['column_1'] ?? $row['date_received'] ?? '')) !!}</td>
                            <td>{!! nl2br(e($row['column_2'] ?? $row['title'] ?? '')) !!}</td>
                        </tr>
                    @empty
                        <tr>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                    @endforelse
                    @if ($segment['include_adjournment'] ?? true)
                        <tr>
                            <td class="ob-print-roman">{{ \App\Support\ObRomanNumeral::display('VIII') }}</td>
                            <td class="ob-print-roman-label">ADJOURNMENT</td>
                        </tr>
                    @endif
                </tbody>
            </table>
            @break

        @case('page_break')
            <div class="ob-print-page-break" aria-hidden="true"></div>
            @break

        @case('heading')
            @break

        @case('legacy_agenda')
            <table class="ob-print-table ob-print-table--agenda-items">
                <tbody>
                    <tr>
                        <td class="ob-print-unfinished-meta">
                            @include('order-of-business.partials.print-agenda-meta', ['row' => $segment['content'] ?? []])
                        </td>
                        <td class="ob-print-unfinished-title">
                            @include('order-of-business.partials.print-agenda-title', ['row' => $segment['content'] ?? []])
                        </td>
                    </tr>
                </tbody>
            </table>
            @break
    @endswitch
@endforeach
