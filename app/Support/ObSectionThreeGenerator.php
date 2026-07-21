<?php

namespace App\Support;

use App\Models\LegislativeSession;
use App\Services\SessionPdfService;

class ObSectionThreeGenerator
{
    public const DEFAULT_VENUE = 'THE SESSION HALL, 6TH FLR., THE BUNKER @ THE CAPITOL COMPOUND, TENEJERO, BALANGA CITY, BATAAN 2100';

    public function bodyForSession(LegislativeSession $session): ?string
    {
        $prior = $session->priorSession;

        if ($prior === null) {
            return null;
        }

        $sessionLabel = $this->sessionLabel($prior);
        $date = $this->sessionDate($prior);
        $venue = $this->sessionVenue($prior);

        if ($date === '') {
            return "READING AND APPROVAL OF THE JOURNAL OF PROCEEDINGS & MINUTES OF THE {$sessionLabel} AT {$venue}";
        }

        return "READING AND APPROVAL OF THE JOURNAL OF PROCEEDINGS & MINUTES OF THE {$sessionLabel} HELD ON {$date} AT {$venue}";
    }

    public function journalUrlFromSession(LegislativeSession $session): ?string
    {
        $pdfs = app(SessionPdfService::class);

        if ($pdfs->publicUrl($session, SessionPdfSlot::FINAL_JOURNAL)) {
            return $pdfs->publicUrl($session, SessionPdfSlot::FINAL_JOURNAL);
        }

        return $pdfs->publicUrl($session, SessionPdfSlot::DRAFT_JOURNAL);
    }

    public function minutesUrlFromSession(LegislativeSession $session): ?string
    {
        $pdfs = app(SessionPdfService::class);

        if ($pdfs->publicUrl($session, SessionPdfSlot::FINAL_MINUTES)) {
            return $pdfs->publicUrl($session, SessionPdfSlot::FINAL_MINUTES);
        }

        return $pdfs->publicUrl($session, SessionPdfSlot::DRAFT_MINUTES);
    }

    /**
     * @param  array<string, mixed>  $blockContent
     */
    public function resolveJournalUrl(LegislativeSession $session, array $blockContent = []): ?string
    {
        if (filled($blockContent['journal_url'] ?? null)) {
            return (string) $blockContent['journal_url'];
        }

        $prior = $session->priorSession;

        return $prior ? $this->journalUrlFromSession($prior) : null;
    }

    /**
     * @param  array<string, mixed>  $blockContent
     */
    public function resolveMinutesUrl(LegislativeSession $session, array $blockContent = []): ?string
    {
        if (filled($blockContent['minutes_url'] ?? null)) {
            return (string) $blockContent['minutes_url'];
        }

        $prior = $session->priorSession;

        return $prior ? $this->minutesUrlFromSession($prior) : null;
    }

    /**
     * @param  array<string, mixed>  $blockContent
     */
    public function linkedBodyHtml(LegislativeSession $session, ?string $body = null, array $blockContent = []): ?string
    {
        $body = strtoupper(trim((string) ($body ?? $this->bodyForSession($session) ?? '')));
        $body = preg_replace('/\bAT\s+AT\b/u', 'AT', $body) ?? $body;

        if ($body === '') {
            return null;
        }

        $journalUrl = $this->resolveJournalUrl($session, $blockContent);
        $minutesUrl = $this->resolveMinutesUrl($session, $blockContent);

        $html = e($body);

        if (filled($journalUrl)) {
            $html = preg_replace(
                '/\bJOURNAL\b/',
                '<a href="'.e($journalUrl).'" class="ob-print-link" target="_blank" rel="noopener">JOURNAL</a>',
                $html,
                1,
            ) ?? $html;
        }

        if (filled($minutesUrl)) {
            $html = preg_replace(
                '/\bMINUTES\b/',
                '<a href="'.e($minutesUrl).'" class="ob-print-link" target="_blank" rel="noopener">MINUTES</a>',
                $html,
                1,
            ) ?? $html;
        }

        $prior = $session->priorSession;
        $highlights = [];

        if ($prior !== null) {
            $highlights = array_filter([
                $this->sessionLabel($prior),
                $this->sessionDate($prior),
                $this->sessionVenue($prior),
            ]);
        }

        if (preg_match('/\bHELD ON ([A-Z]+ \d{1,2}, \d{4})\b/u', $body, $dateMatch) === 1) {
            $highlights[] = $dateMatch[1];
        }

        foreach (array_unique($highlights) as $highlight) {
            $escaped = e($highlight);

            if ($escaped === '' || ! str_contains($html, $escaped)) {
                continue;
            }

            if (str_contains($html, '<span class="ob-print-section-three-highlight">'.$escaped.'</span>')) {
                continue;
            }

            $html = str_replace(
                $escaped,
                '<span class="ob-print-section-three-highlight">'.$escaped.'</span>',
                $html,
            );
        }

        $html = preg_replace(
            '/(\d+)(ST|ND|RD|TH)\b/u',
            '$1<sup class="ob-print-ordinal-suffix">$2</sup>',
            $html,
        ) ?? $html;

        return $html;
    }

    private function sessionLabel(LegislativeSession $session): string
    {
        $label = strtoupper(trim((string) ($session->session_number ?: '')));

        return $label !== ''
            ? $label
            : strtoupper($session->sessionKindLabel()).' SESSION';
    }

    private function sessionDate(LegislativeSession $session): string
    {
        return $session->session_date
            ? strtoupper($session->session_date->format('F j, Y'))
            : '';
    }

    private function sessionVenue(LegislativeSession $session): string
    {
        $venue = strtoupper(trim((string) ($session->venue ?: self::DEFAULT_VENUE)));

        return preg_replace('/^AT\s+/u', '', $venue) ?? $venue;
    }
}
