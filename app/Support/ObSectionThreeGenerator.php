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

        $sessionLabel = strtoupper(trim((string) ($prior->session_number ?: '')));

        if ($sessionLabel === '') {
            $sessionLabel = strtoupper($prior->sessionKindLabel()).' SESSION';
        }

        $date = $prior->session_date
            ? strtoupper($prior->session_date->format('F j, Y'))
            : '';

        $venue = strtoupper(trim((string) ($prior->venue ?: self::DEFAULT_VENUE)));

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

        return $html;
    }
}
