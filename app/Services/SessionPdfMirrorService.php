<?php

namespace App\Services;

use App\Models\LegislativeSession;
use App\Support\MediaType;
use App\Support\SessionPdfSlot;
use Illuminate\Support\Facades\Log;
use Throwable;

class SessionPdfMirrorService
{
    public function __construct(
        protected GoogleDrivePdfDownloader $downloader,
        protected SessionPdfService $pdfs,
        protected SessionCommitteeReportFileService $committeeReports,
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
            $forceFormat = SessionPdfSlot::acceptsOfficeDocuments($slot) ? null : 'pdf';
            $file = $this->downloader->downloadFile($url, $forceFormat);
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
     * Download every supported file from the Committee Reports Google Drive folder.
     *
     * @return array{mirrored:int,skipped:int,failed:int,messages:list<string>}
     */
    public function mirrorCommitteeReportsFolder(LegislativeSession $session, ?int $userId = null): array
    {
        $url = trim((string) ($session->pdf_committee_reports ?? ''));
        $mirrored = 0;
        $skipped = 0;
        $failed = 0;
        $messages = [];

        if ($url === '') {
            return [
                'mirrored' => 0,
                'skipped' => 0,
                'failed' => 0,
                'messages' => ['No Committee Reports folder URL to download.'],
            ];
        }

        try {
            $entries = $this->downloader->listFolderFiles($url);
        } catch (Throwable $e) {
            Log::warning('Committee Reports folder listing failed', [
                'session_id' => $session->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'mirrored' => 0,
                'skipped' => 0,
                'failed' => 1,
                'messages' => ['Committee Reports folder: '.$e->getMessage()],
            ];
        }

        foreach ($entries as $entry) {
            $displayName = $this->committeeReportDisplayName($entry);

            if ($this->committeeReports->hasFileNamed($session, $displayName)) {
                $skipped++;

                continue;
            }

            try {
                $forceFormat = in_array($entry['kind'], ['document', 'spreadsheets', 'presentation'], true)
                    ? 'pdf'
                    : null;
                $file = $this->downloader->downloadFile($entry['url'], $forceFormat);

                if (! $this->isSupportedCommitteeReportMedia($file['mime'], $file['extension'])) {
                    $skipped++;
                    $messages[] = $entry['name'].': unsupported file type, skipped.';

                    continue;
                }

                $this->committeeReports->storeBytes(
                    $file['contents'],
                    $session,
                    $displayName,
                    $file['extension'],
                    $file['mime'],
                    $userId,
                    strlen($file['contents']),
                );
                $mirrored++;
            } catch (Throwable $e) {
                $failed++;
                $messages[] = $entry['name'].': '.$e->getMessage();
                Log::warning('Committee report folder file mirror failed', [
                    'session_id' => $session->id,
                    'file_id' => $entry['id'],
                    'name' => $entry['name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($mirrored === 0 && $failed === 0 && $skipped > 0 && $messages === []) {
            $messages[] = 'All Committee Reports folder files are already stored locally.';
        }

        return compact('mirrored', 'skipped', 'failed', 'messages');
    }

    /**
     * @return array{mirrored:int,skipped:int,failed:int,messages:list<string>}
     */
    public function mirrorAllFor(LegislativeSession $session, bool $overwrite = false, ?int $userId = null): array
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

        $folder = $this->mirrorCommitteeReportsFolder($session, $userId);
        $mirrored += $folder['mirrored'];
        $skipped += $folder['skipped'];
        $failed += $folder['failed'];
        $messages = array_merge($messages, $folder['messages']);

        return compact('mirrored', 'skipped', 'failed', 'messages');
    }

    /**
     * @param  array{id: string, name: string, url: string, kind: string}  $entry
     */
    protected function committeeReportDisplayName(array $entry): string
    {
        $name = trim($entry['name']);

        if ($name === '') {
            $name = $entry['id'];
        }

        if (in_array($entry['kind'], ['document', 'spreadsheets', 'presentation'], true)
            && ! preg_match('/\.(pdf|docx?|pptx?|xlsx?)$/i', $name)) {
            return $name.'.pdf';
        }

        return $name;
    }

    protected function isSupportedCommitteeReportMedia(string $mime, string $extension): bool
    {
        $extension = strtolower($extension);

        if (in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return true;
        }

        return MediaType::isPdfMime($mime) || MediaType::isImageMime($mime);
    }
}
