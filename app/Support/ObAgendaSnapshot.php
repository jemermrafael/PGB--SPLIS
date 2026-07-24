<?php

namespace App\Support;

use App\Models\AgendaItem;
use App\Models\BoardMember;

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
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    public static function agendaNosFromContent(array $content): array
    {
        if (! empty($content['agenda_nos']) && is_array($content['agenda_nos'])) {
            return array_values(array_filter(
                array_map(fn ($no) => trim((string) $no), $content['agenda_nos']),
                fn (string $no) => $no !== '',
            ));
        }

        $no = self::displayAgendaNo($content);

        return $no === '—' ? [] : [$no];
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public static function displayAgendaNosLabel(array $content): string
    {
        $nos = self::agendaNosFromContent($content);

        if ($nos === []) {
            return 'Agenda No. —';
        }

        if (count($nos) === 1) {
            return 'Agenda No. '.$nos[0];
        }

        return 'Agenda Nos. '.implode(', ', $nos);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public static function displayAgendaNosLabelHtml(array $content): string
    {
        $nos = self::agendaNosFromContent($content);

        if ($nos === []) {
            return e('Agenda No. —');
        }

        $label = self::displayAgendaNosLabel($content);
        $links = is_array($content['agenda_no_links'] ?? null) ? $content['agenda_no_links'] : [];

        $urlsForNos = array_map(function (string $no) use ($links): ?string {
            $url = $links[$no] ?? null;

            return filled($url) ? (string) $url : null;
        }, $nos);

        $filledUrls = array_values(array_filter($urlsForNos, fn (?string $url) => $url !== null));

        // Same committee report for every agenda no. → one link over the whole label.
        if (
            count($filledUrls) === count($nos)
            && count(array_unique($filledUrls)) === 1
        ) {
            return '<a href="'.e($filledUrls[0]).'" class="ob-print-link" target="_blank" rel="noopener">'
                .e($label)
                .'</a>';
        }

        $prefix = count($nos) === 1 ? 'Agenda No. ' : 'Agenda Nos. ';

        $parts = array_map(function (string $no) use ($links): string {
            $url = $links[$no] ?? null;

            if (filled($url)) {
                return '<a href="'.e((string) $url).'" class="ob-print-link" target="_blank" rel="noopener">'.e($no).'</a>';
            }

            return e($no);
        }, $nos);

        return e($prefix).implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public static function displayAgendaNoHtml(array $content): string
    {
        $no = self::displayAgendaNo($content);

        if ($no === '—') {
            return e('—');
        }

        $url = $content['request_pdf_url'] ?? null;

        if (filled($url)) {
            return '<a href="'.e((string) $url).'" class="ob-print-link" target="_blank" rel="noopener">'.e($no).'</a>';
        }

        return e($no);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public static function committeeReportKey(array $content): string
    {
        $committeeId = $content['committee_id'] ?? null;

        if (is_numeric($committeeId) && (int) $committeeId > 0) {
            return 'id:'.(int) $committeeId;
        }

        return 'name:'.ObCommitteeFormatter::spCommitteeLabel((string) ($content['committee_name'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public static function mergeCommitteeReportRows(array $existing, array $incoming): array
    {
        $nos = array_values(array_unique(array_merge(
            self::agendaNosFromContent($existing),
            self::agendaNosFromContent($incoming),
        )));

        $existing['agenda_nos'] = $nos;

        if ($nos !== []) {
            $existing['agenda_no'] = $nos[0];
        }

        $existingLinks = is_array($existing['agenda_no_links'] ?? null) ? $existing['agenda_no_links'] : [];
        $incomingLinks = is_array($incoming['agenda_no_links'] ?? null) ? $incoming['agenda_no_links'] : [];
        $mergedLinks = array_filter(
            array_merge($existingLinks, $incomingLinks),
            fn ($url) => filled($url),
        );

        if ($mergedLinks !== []) {
            $existing['agenda_no_links'] = $mergedLinks;
        }

        return $existing;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function enrichCommitteeReportRow(array $content): array
    {
        $committeeId = $content['committee_id'] ?? null;
        $committeeName = (string) ($content['committee_name'] ?? '');

        $content['committee_name'] = ObCommitteeFormatter::resolvedReportLabel(
            is_numeric($committeeId) ? (int) $committeeId : null,
            $committeeName,
        );

        $chair = CommitteeLookup::obChairFor(
            is_numeric($committeeId) ? (int) $committeeId : null,
            $committeeName,
        );

        if ($chair !== '') {
            $content['chair_name'] = $chair;
        }

        if (is_numeric($committeeId) && (int) $committeeId > 0) {
            $content['committee_id'] = (int) $committeeId;
        } elseif ($committee = CommitteeLookup::findByName($committeeName)) {
            $content['committee_id'] = $committee->id;
        }

        if (CommitteeLookup::findById(is_numeric($committeeId) ? (int) $committeeId : null)
            ?? CommitteeLookup::findByName($committeeName)) {
            $content['needs_committee'] = false;
        }

        return $content;
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
            'committee_name' => ObCommitteeFormatter::resolvedReportLabel($committee?->id, $referral),
            'chair_name' => CommitteeLookup::obChairFor($committee?->id, $referral),
            'needs_committee' => blank($item->committee_referred),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function unfinishedAgenda(AgendaItem $item): array
    {
        $committee = filled($item->committee_referred)
            ? CommitteeLookup::findByName($item->committee_referred)
            : null;

        return self::enrichUnfinishedRow(self::agendaItemRow($item, [
            'committee_id' => $committee?->id,
            'committee_name' => ObCommitteeFormatter::resolvedLabel($committee?->id, $item->committee_referred),
            'needs_committee' => blank($item->committee_referred),
        ]), $item);
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
            'referral_note' => match ($kind) {
                'urgent' => self::unassignedUrgentReferralNote($item),
                'regular' => self::unassignedRegularReferralNote($item),
                default => self::referralNote($item),
            },
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
            'sender' => (string) ($item->sender ?? ''),
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

    public static function unassignedUrgentReferralNote(AgendaItem $item): string
    {
        return self::unassignedUrgentReferralNoteFromReferral($item->committee_referred);
    }

    public static function unassignedUrgentReferralNoteFromReferral(?string $referral): string
    {
        $referral = trim((string) $referral);
        if ($referral === '') {
            return '';
        }

        $committee = CommitteeLookup::findByName($referral);
        $committeeLabel = ObCommitteeFormatter::resolvedReportLabel($committee?->id, $referral);

        if ($committeeLabel === '') {
            return '';
        }

        $chair = CommitteeLookup::obChairFor($committee?->id, $referral);

        $note = 'Sponsored by: '.$committeeLabel;
        if ($chair !== '') {
            $note .= "\nChaired by: ".$chair;
        }

        return $note;
    }

    public static function unassignedRegularReferralNote(AgendaItem $item): string
    {
        return self::unassignedRegularReferralNoteFromReferral($item->committee_referred);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected static function unassignedRegularReferralSource(array $content, ?AgendaItem $item = null): ?string
    {
        if (filled($item?->committee_referred)) {
            return (string) $item->committee_referred;
        }

        $committeeId = $content['committee_id'] ?? null;
        if (is_numeric($committeeId) && (int) $committeeId > 0) {
            return CommitteeLookup::findById((int) $committeeId)?->name;
        }

        return null;
    }

    public static function unassignedRegularReferralNoteFromReferral(?string $referral): string
    {
        $referral = trim((string) $referral);
        if ($referral === '') {
            return '';
        }

        $committee = CommitteeLookup::findByName($referral);
        $committeeLabel = ObCommitteeFormatter::spCommitteeReportLabel($referral);

        if ($committeeLabel === '') {
            return '';
        }

        $chair = CommitteeLookup::obChairFor($committee?->id, $referral);

        $note = 'To be referred to '.$committeeLabel;
        if ($chair !== '') {
            $note .= ",\nChaired by: ".$chair;
        }

        return '('.$note.')';
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function enrichUnassignedRow(array $content, ?AgendaItem $item = null): array
    {
        return match ($content['kind'] ?? 'regular') {
            'urgent' => self::enrichUnassignedUrgentRow($content, $item),
            default => self::enrichUnassignedRegularRow($content, $item),
        };
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function enrichUnassignedUrgentRow(array $content, ?AgendaItem $item = null): array
    {
        if (($content['kind'] ?? 'regular') !== 'urgent') {
            return $content;
        }

        if (! filled($item?->committee_referred)) {
            return $content;
        }

        $note = self::unassignedUrgentReferralNoteFromReferral($item->committee_referred);
        if ($note !== '') {
            $content['referral_note'] = $note;
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function enrichUnassignedRegularRow(array $content, ?AgendaItem $item = null): array
    {
        if (($content['kind'] ?? 'regular') !== 'regular') {
            return $content;
        }

        $title = (string) ($item?->title ?? $content['title'] ?? '');
        $sender = (string) ($item?->sender ?? $content['sender'] ?? '');
        $formatted = self::formatRegularUnassignedTitle($title, $sender);
        $content['title'] = $formatted['title'];
        $content['sender'] = $sender;

        if ($formatted['filer_note'] !== '') {
            $content['filer_note'] = $formatted['filer_note'];
        } else {
            unset($content['filer_note']);
        }

        $referral = self::unassignedRegularReferralSource($content, $item);
        if ($referral === null || trim($referral) === '') {
            return $content;
        }

        $note = self::unassignedRegularReferralNoteFromReferral($referral);
        if ($note !== '') {
            $content['referral_note'] = $note;
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function enrichUnfinishedRow(array $content, ?AgendaItem $item = null): array
    {
        $title = (string) ($content['title'] ?? $item?->title ?? '');
        $sender = (string) ($content['sender'] ?? $item?->sender ?? '');
        $formatted = self::formatRegularUnassignedTitle($title, $sender);

        $content['title'] = $formatted['title'];
        $content['sender'] = $sender;

        if ($formatted['filer_note'] !== '') {
            $content['filer_note'] = $formatted['filer_note'];
        } else {
            unset($content['filer_note']);
        }

        return $content;
    }

    /**
     * Separate inconsistent "Filed By" text from a title and normalize it for print.
     *
     * @return array{title:string,filer_note:string}
     */
    public static function formatRegularUnassignedTitle(string $title, ?string $sender): array
    {
        $title = trim($title);
        $filer = '';

        if (preg_match('/\s*\(?\s*Filed\s+By\s*:\s*(.+?)\s*\)?\s*$/isu', $title, $matches)) {
            $filer = self::cleanFilerName($matches[1]);
            $title = trim(mb_substr($title, 0, mb_strlen($title) - mb_strlen($matches[0])));
        }

        $title = preg_replace_callback(
            '/(\bentitled\s*)"([^"]+)"/iu',
            fn (array $matches): string => $matches[1].'“'.trim($matches[2]).'”',
            $title,
        ) ?? $title;

        if ($filer === '') {
            $filer = self::filerFromSender($sender);
        }

        return [
            'title' => trim($title),
            'filer_note' => $filer !== '' ? '(Filed By: '.$filer.')' : '',
        ];
    }

    protected static function cleanFilerName(string $filer): string
    {
        return trim($filer, " \t\n\r\0\x0B().");
    }

    protected static function filerFromSender(?string $sender): string
    {
        $sender = trim((string) $sender);

        if ($sender === '') {
            return '';
        }

        if (preg_match('/^VG\s+Cris$/iu', $sender)) {
            return 'Vice Governor Ma. Cristina M. Garcia';
        }

        if (! preg_match('/^BM\s+(.+)$/iu', $sender, $matches)) {
            return '';
        }

        $names = preg_split('/\s*(?:&|\band\b)\s*/iu', trim($matches[1])) ?: [];

        return collect($names)
            ->map(function (string $name): string {
                $name = trim($name);
                if ($name === '') {
                    return '';
                }

                $member = BoardMember::query()
                    ->where('name', 'like', '%'.$name.'%')
                    ->orderByDesc('is_active')
                    ->first();

                return $member?->orderOfBusinessName() ?: 'Board Member '.$name;
            })
            ->filter()
            ->implode(' and ');
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
