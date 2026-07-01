<?php

namespace App\Support;

use App\Models\AgendaItem;

class ObAgendaSnapshot
{
    public static function agendaNo(AgendaItem $item): string
    {
        if (filled($item->tracking_no)) {
            return (string) $item->tracking_no;
        }

        return (string) $item->id;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public static function displayAgendaNo(array $content): string
    {
        $no = $content['agenda_no'] ?? $content['session_agenda_no'] ?? null;

        return $no !== null && $no !== '' ? (string) $no : '—';
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromAgendaItem(AgendaItem $item): array
    {
        return self::unassignedAgenda($item, 'regular');
    }

    /**
     * @return array<string, mixed>
     */
    public static function committeeReport(AgendaItem $item, int $rowNo, ?int $committeeId = null): array
    {
        if (filled($item->committee_referred)) {
            $committee = CommitteeLookup::findByName($item->committee_referred);
            $referral = $item->committee_referred;
        } elseif ($committeeId !== null) {
            $committee = CommitteeLookup::findById($committeeId);
            $referral = $committee?->name;
        } else {
            $committee = null;
            $referral = null;
        }

        return [
            'row_no' => $rowNo,
            'agenda_no' => self::agendaNo($item),
            'committee_id' => $committee?->id,
            'committee_name' => ObCommitteeFormatter::spCommitteeReportLabel($referral),
            'chair_name' => CommitteeLookup::chairFor($committee?->id, $referral),
            'needs_committee' => blank($item->committee_referred),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function unfinishedAgenda(AgendaItem $item): array
    {
        return self::agendaItemRow($item, [
            'committee_id' => null,
            'committee_name' => ObCommitteeFormatter::spCommitteeLabel($item->committee_referred),
            'needs_committee' => blank($item->committee_referred),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function readingAgenda(AgendaItem $item, string $reading): array
    {
        return self::agendaItemRow($item, [
            'reading' => $reading,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function unassignedAgenda(AgendaItem $item, string $kind): array
    {
        return self::agendaItemRow($item, [
            'kind' => $kind,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected static function agendaItemRow(AgendaItem $item, array $extra = []): array
    {
        return array_merge([
            'agenda_no' => self::agendaNo($item),
            'date_received' => $item->date_received?->format('F j, Y') ?? '',
            'prescription' => self::prescriptionLabel($item),
            'title' => (string) ($item->title ?? ''),
            'referral_note' => self::referralNote($item),
        ], $extra);
    }

    public static function referralNote(AgendaItem $item): string
    {
        if ($item->date_of_referral) {
            return '(Referred last '.$item->date_of_referral->format('F j, Y').')';
        }

        return '';
    }

    public static function prescriptionLabel(AgendaItem $item): string
    {
        if ($item->due_date) {
            return $item->due_date->format('F j, Y');
        }

        if ($item->prescribed_days !== null && (int) $item->prescribed_days > 0) {
            return (string) $item->prescribed_days.' days';
        }

        return 'No due date';
    }
}
