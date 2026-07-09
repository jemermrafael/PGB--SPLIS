<?php

namespace App\Services;

use App\Enums\ObBlockType;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Support\ObRomanNumeral;
use App\Support\ObSectionThreeGenerator;

class ObSectionThreeSyncService
{
    public function __construct(
        protected ObSectionThreeGenerator $generator,
    ) {}

    public function syncForSession(LegislativeSession $session, bool $force = false): bool
    {
        $session->loadMissing(['priorSession', 'obDocument.blocks']);

        $document = $session->obDocument;

        if ($document === null) {
            return false;
        }

        $block = $this->sectionThreeBlock($document);

        if ($block === null) {
            return false;
        }

        $content = $block->content ?? [];
        $updated = false;

        $body = $this->generator->bodyForSession($session);

        if ($body !== null) {
            $currentBody = trim((string) ($content['body'] ?? ''));

            if ($force || $currentBody === '') {
                $content['body'] = $body;
                $updated = true;
            }
        }

        $prior = $session->priorSession;

        if ($prior !== null) {
            $journalUrl = $this->generator->journalUrlFromSession($prior);
            $minutesUrl = $this->generator->minutesUrlFromSession($prior);

            if (($force || blank($content['journal_url'] ?? null)) && filled($journalUrl)) {
                $content['journal_url'] = $journalUrl;
                $updated = true;
            }

            if (($force || blank($content['minutes_url'] ?? null)) && filled($minutesUrl)) {
                $content['minutes_url'] = $minutesUrl;
                $updated = true;
            }
        }

        if (! $updated) {
            return false;
        }

        $block->update(['content' => $content]);

        return true;
    }

    protected function sectionThreeBlock(ObDocument $document): ?ObBlock
    {
        return $document->blocks
            ->first(function (ObBlock $block): bool {
                if ($block->type !== ObBlockType::RomanSection) {
                    return false;
                }

                return ObRomanNumeral::normalize($block->content['numeral'] ?? '') === 'III';
            });
    }
}
