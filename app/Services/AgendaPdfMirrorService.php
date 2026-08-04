<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Support\AgendaPdfSlot;
use App\Support\MediaType;
use Illuminate\Support\Facades\Log;
use Throwable;

class AgendaPdfMirrorService
{
    public function __construct(
        protected GoogleDrivePdfDownloader $downloader,
        protected AgendaPdfService $pdfs,
        protected AgendaItemRequestFileService $requestFiles,
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

        if ($slot === AgendaPdfSlot::REQUEST && $this->downloader->extractFolderId($url) !== null) {
            return [
                'ok' => false,
                'message' => 'Request PDF URL is a Drive folder — use the request packet mirror.',
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
     * Download a Drive folder (with nested subfolders) into agenda request packet files.
     *
     * @return array{mirrored: int, skipped: int, failed: int, messages: list<string>}
     */
    public function mirrorRequestPacketFolder(AgendaItem $agenda, ?string $folderUrl = null): array
    {
        $url = trim((string) ($folderUrl ?? $agenda->request_pdf_url ?? ''));
        $mirrored = 0;
        $skipped = 0;
        $failed = 0;
        $messages = [];

        if ($url === '' || $this->downloader->extractFolderId($url) === null) {
            return [
                'mirrored' => 0,
                'skipped' => 0,
                'failed' => 0,
                'messages' => [],
            ];
        }

        try {
            $entries = $this->downloader->listFolderFiles($url, recursive: true);
        } catch (Throwable $e) {
            Log::warning('Agenda request packet folder listing failed', [
                'agenda_id' => $agenda->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'mirrored' => 0,
                'skipped' => 0,
                'failed' => 1,
                'messages' => ['Request packet folder: '.$e->getMessage()],
            ];
        }

        foreach ($entries as $entry) {
            $displayName = trim((string) ($entry['name'] ?? ''));
            if ($displayName === '') {
                $displayName = (string) $entry['id'];
            }
            if (! str_contains(strtolower($displayName), '.') && in_array($entry['kind'], ['document', 'spreadsheets', 'presentation'], true)) {
                $displayName .= '.pdf';
            }

            $folder = $entry['relative_folder'] ?? null;

            if ($this->requestFiles->hasFileInFolder($agenda, $displayName, $folder)) {
                $skipped++;

                continue;
            }

            try {
                $forceFormat = in_array($entry['kind'], ['document', 'spreadsheets', 'presentation'], true)
                    ? 'pdf'
                    : null;
                $file = $this->downloader->downloadFile($entry['url'], $forceFormat);

                if (! $this->isSupportedMedia($file['mime'], $file['extension'])) {
                    $skipped++;
                    $messages[] = $displayName.': unsupported file type, skipped.';

                    continue;
                }

                $this->requestFiles->storeBytes(
                    $file['contents'],
                    $agenda,
                    $displayName,
                    $folder,
                    $file['extension'],
                    $file['mime'],
                    null,
                    strlen($file['contents']),
                );
                $mirrored++;
            } catch (Throwable $e) {
                $failed++;
                $messages[] = $displayName.': '.$e->getMessage();
                Log::warning('Agenda request packet file mirror failed', [
                    'agenda_id' => $agenda->id,
                    'file_id' => $entry['id'],
                    'name' => $displayName,
                    'folder' => $folder,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($mirrored === 0 && $failed === 0 && $skipped > 0 && $messages === []) {
            $messages[] = 'All request packet files are already registered locally.';
        }

        return compact('mirrored', 'skipped', 'failed', 'messages');
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

        $imported = $this->requestFiles->importFromDisk($agenda);
        $mirrored += $imported['registered'];
        $skipped += $imported['skipped'] > 0 && $imported['registered'] === 0 ? 0 : 0;
        if ($imported['registered'] > 0) {
            $messages[] = $imported['registered'].' local request packet file(s) registered from disk.';
        }

        $requestUrl = trim((string) ($agenda->request_pdf_url ?? ''));
        if ($requestUrl !== '' && $this->downloader->extractFolderId($requestUrl) !== null) {
            $packet = $this->mirrorRequestPacketFolder($agenda, $requestUrl);
            $mirrored += $packet['mirrored'];
            $skipped += $packet['skipped'];
            $failed += $packet['failed'];
            $messages = array_merge($messages, $packet['messages']);
        }

        foreach (AgendaPdfSlot::all() as $slot) {
            if (
                $slot === AgendaPdfSlot::REQUEST
                && $requestUrl !== ''
                && $this->downloader->extractFolderId($requestUrl) !== null
            ) {
                continue;
            }

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

    protected function isSupportedMedia(string $mime, string $extension): bool
    {
        if (MediaType::isImageMime($mime) || $mime === 'application/pdf') {
            return true;
        }

        return in_array(strtolower($extension), ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx'], true);
    }
}
