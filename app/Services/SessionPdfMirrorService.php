<?php

namespace App\Services;

use App\Models\LegislativeSession;
use App\Support\SessionPdfSlot;
use Illuminate\Support\Facades\Log;
use Throwable;

class SessionPdfMirrorService
{
    public function __construct(
        protected GoogleDrivePdfDownloader $downloader,
        protected SessionPdfService $pdfs,
    ) {}

    /**
     * @return array{ok: bool, message: string, path?: string, slot?: string}
     */
    public function mirror(LegislativeSession $session, string $slot, bool $overwrite = false): array
    {
        if (! SessionPdfSlot::isValid($slot)) {
            return ['ok' => false, 'message' => 'Unknown session PDF slot.', 'slot' => $slot];
        }

        if (! SessionPdfSlot::isMirrorable($slot)) {
            return ['ok' => false, 'message' => 'This slot is a folder link and cannot be mirrored as a single PDF.', 'slot' => $slot];
        }

        $config = SessionPdfSlot::config($slot);

        if (! $overwrite && $this->pdfs->existsFor($session, $slot)) {
            return [
                'ok' => true,
                'message' => $config['label'].' already present locally; skipped.',
                'path' => $session->{$config['path']},
                'slot' => $slot,
            ];
        }

        $url = trim((string) ($session->{$config['field']} ?? ''));

        if ($url === '') {
            return [
                'ok' => false,
                'message' => 'No '.$config['label'].' URL to download.',
                'slot' => $slot,
            ];
        }

        try {
            $file = $this->downloader->downloadFile($url);
            $path = $this->pdfs->storeBytes($file['contents'], $session, $slot, $file['extension']);
            $session->update([$config['path'] => $path]);

            return [
                'ok' => true,
                'message' => $config['label'].' mirrored to '.$path,
                'path' => $path,
                'slot' => $slot,
            ];
        } catch (Throwable $e) {
            Log::warning('Session PDF mirror failed', [
                'session_id' => $session->id,
                'slot' => $slot,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => $config['label'].': '.$e->getMessage(),
                'slot' => $slot,
            ];
        }
    }

    /**
     * @return array{mirrored:int,skipped:int,failed:int,messages:list<string>}
     */
    public function mirrorAllFor(LegislativeSession $session, bool $overwrite = false): array
    {
        $mirrored = 0;
        $skipped = 0;
        $failed = 0;
        $messages = [];

        foreach (SessionPdfSlot::mirrorable() as $slot) {
            $result = $this->mirror($session, $slot, $overwrite);

            if ($result['ok'] && str_contains($result['message'], 'skipped')) {
                $skipped++;
            } elseif ($result['ok']) {
                $mirrored++;
                $messages[] = $result['message'];
            } elseif (str_contains($result['message'], 'No ') && str_contains($result['message'], ' URL to download')) {
                continue;
            } else {
                $failed++;
                $messages[] = $result['message'];
            }
        }

        return compact('mirrored', 'skipped', 'failed', 'messages');
    }
}
