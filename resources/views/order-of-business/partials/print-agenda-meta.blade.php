@php
    $isEmpty = $empty ?? false;
    $agendaNo = \App\Support\ObAgendaSnapshot::displayAgendaNo($row ?? []);
@endphp
@if ($isEmpty)
    <p>Agenda No.</p>
@else
    <p>
        Agenda No.
        <span class="ob-print-agenda-number">{!! \App\Support\ObAgendaSnapshot::displayAgendaNoHtml($row ?? []) !!}</span>
    </p>
    <p class="ob-print-meta-break" aria-hidden="true">&nbsp;</p>
    <p>Date of Receipt:</p>
    <p>{{ $row['date_received'] ?? '—' }}</p>
    <p class="ob-print-meta-break" aria-hidden="true">&nbsp;</p>
    <p>Prescription:</p>
    <p>{{ $row['prescription'] ?? '—' }}</p>
@endif
