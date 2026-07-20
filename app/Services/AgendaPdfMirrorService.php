<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Support\AgendaPdfSlot;
use Illuminate\Support\Facades\Log;
use Throwable;

class AgendaPdfMirrorService
{
    public function __construct(
        protected GoogleDrivePdfDownloader $downloader,
        protected AgendaPdfService $pdfs,
    ) {}

    /**
     * @return array{ok: bool, message: string, path?: string, slot?: string}
     */
    public function mirror(AgendaItem $agenda, string $slot, bool $overwrite = false): array
    {
        if (! AgendaPdfSlot::isValid($slot)) {
            return ['ok' => false, 'message' => 'Unknown document slot.', 'slot' => $slot];
        }

        $config = AgendaPdfSlot::config($slot);

        if (! $overwrite && $this->pdfs->existsFor($agenda, $slot)) {
            return [
                'ok' => true,
                'message' => $config['label'].' already present locally; skipped.',
                'path' => $agenda->{$config['path']},
                'slot' => $slot,
            ];
        }

        $url = trim((string) ($agenda->{$config['url']} ?? ''));

        if ($url === '') {
            return [
                'ok' => false,
                'message' => 'No '.$config['label'].' URL to download.',
                'slot' => $slot,
            ];
        }

        try {
            $file = $this->downloader->downloadFile($url);
            $path = $this->pdfs->storeBytes($file['contents'], $agenda, $slot, $file['extension']);
            $agenda->update([$config['path'] => $path]);

            return [
                'ok' => true,
                'message' => $config['label'].' mirrored to '.$path,
                'path' => $path,
                'slot' => $slot,
            ];
        } catch (Throwable $e) {
            Log::warning('Agenda PDF mirror failed', [
                'agenda_id' => $agenda->id,
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
     * @return array{mirrored: int, skipped: int, failed: int, messages: list<string>}
     */
    public function mirrorAllFor(AgendaItem $agenda, bool $overwrite = false): array
    {
        $mirrored = 0;
        $skipped = 0;
        $failed = 0;
        $messages = [];

        foreach (AgendaPdfSlot::all() as $slot) {
            $result = $this->mirror($agenda, $slot, $overwrite);

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
