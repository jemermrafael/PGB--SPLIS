<?php

namespace App\Services;

use App\Support\MediaType;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDrivePdfDownloader
{
    /**
     * Download a PDF from a Google Drive share/view URL (or any direct PDF URL).
     *
     * @throws RuntimeException
     */
    public function download(string $url): string
    {
        return $this->downloadFile($url)['contents'];
    }

    /**
     * Download a PDF, image, or Word file from Google Drive / direct URL.
     *
     * @param  ?string  $forceFormat  Optional Docs export format override (e.g. "pdf").
     * @return array{contents: string, mime: string, extension: string}
     *
     * @throws RuntimeException
     */
    public function downloadFile(string $url, ?string $forceFormat = null): array
    {
        $url = trim($url);

        if ($url === '') {
            throw new RuntimeException('File URL is empty.');
        }

        $docsExport = $this->docsEditorExport($url, $forceFormat);

        if ($docsExport !== null) {
            return $this->downloadGoogleDocExport($docsExport['url'], $docsExport['extension']);
        }

        $fileId = $this->extractFileId($url);

        if ($fileId !== null) {
            return $this->downloadDriveFile($fileId);
        }

        return $this->downloadDirect($url);
    }

    public function extractFileId(string $url): ?string
    {
        if (preg_match('#drive\.google\.com/file/d/([^/]+)#i', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('#drive\.google\.com/(?:open|uc)\?(?:.*&)?id=([^&]+)#i', $url, $matches)) {
            return rawurldecode($matches[1]);
        }

        if (preg_match('#docs\.google\.com/.*?[?&]id=([^&]+)#i', $url, $matches)) {
            return rawurldecode($matches[1]);
        }

        return null;
    }

    public function extractFolderId(string $url): ?string
    {
        if (preg_match('~drive\.google\.com/drive/(?:u/\d+/)?folders/([^/?#]+)~i', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('~drive\.google\.com/embeddedfolderview\?(?:.*&)?id=([^&]+)~i', $url, $matches)) {
            return rawurldecode($matches[1]);
        }

        return null;
    }

    /**
     * List files inside a publicly shared Google Drive folder.
     *
     * @return list<array{id: string, name: string, url: string, kind: string}>
     *
     * @throws RuntimeException
     */
    public function listFolderFiles(string $folderUrl): array
    {
        $folderId = $this->extractFolderId($folderUrl);

        if ($folderId === null) {
            throw new RuntimeException('Not a Google Drive folder URL.');
        }

        $response = $this->httpClient()->get(
            'https://drive.google.com/embeddedfolderview?id='.rawurlencode($folderId).'#list'
        );

        if (! $response->successful()) {
            throw new RuntimeException('Could not open Google Drive folder (HTTP '.$response->status().'). The folder may be private.');
        }

        $html = $response->body();
        $files = [];
        $seen = [];

        // Regular Drive files: /file/d/{id}/...
        if (preg_match_all(
            '#href="(https://drive\.google\.com/file/d/([^/"?]+)/[^"]*)"[^>]*>(.*?)</a>#is',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $id = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5);
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $name = trim(html_entity_decode(strip_tags($match[3]), ENT_QUOTES | ENT_HTML5));
                $files[] = [
                    'id' => $id,
                    'name' => $name !== '' ? $name : $id,
                    'url' => 'https://drive.google.com/file/d/'.$id.'/view',
                    'kind' => 'file',
                ];
            }
        }

        // Native Google Docs / Sheets / Slides linked from the folder view.
        if (preg_match_all(
            '#href="(https://docs\.google\.com/(document|spreadsheets|presentation)/d/([^/"?]+)/[^"]*)"[^>]*>(.*?)</a>#is',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $id = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5);
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $name = trim(html_entity_decode(strip_tags($match[4]), ENT_QUOTES | ENT_HTML5));
                $files[] = [
                    'id' => $id,
                    'name' => $name !== '' ? $name : $id,
                    'url' => 'https://docs.google.com/'.$match[2].'/d/'.$id.'/edit',
                    'kind' => strtolower($match[2]),
                ];
            }
        }

        // Fallback: data-id attributes when anchor text parsing fails.
        if ($files === [] && preg_match_all('/data-id="([^"]+)"/', $html, $idMatches)) {
            foreach (array_unique($idMatches[1]) as $id) {
                if ($id === $folderId || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $files[] = [
                    'id' => $id,
                    'name' => $id,
                    'url' => 'https://drive.google.com/file/d/'.$id.'/view',
                    'kind' => 'file',
                ];
            }
        }

        if ($files === []) {
            throw new RuntimeException('No files found in the Google Drive folder (empty, private, or unsupported listing).');
        }

        return array_values($files);
    }

    /**
     * Detect a Google Docs/Sheets/Slides editor URL and build its export URL.
     * Handles both native Google files and uploaded Office files (rtpof=true).
     *
     * @return array{url: string, extension: string}|null
     */
    protected function docsEditorExport(string $url, ?string $forceFormat = null): ?array
    {
        if (! preg_match('~docs\.google\.com/(document|spreadsheets|presentation)/d/([^/?#]+)~i', $url, $matches)) {
            return null;
        }

        $kind = strtolower($matches[1]);
        $id = $matches[2];
        $forceFormat = $forceFormat !== null ? strtolower($forceFormat) : null;

        if ($forceFormat === 'pdf') {
            [$format, $extension] = ['pdf', 'pdf'];
        } else {
            [$format, $extension] = match ($kind) {
                'spreadsheets' => ['xlsx', 'xlsx'],
                'presentation' => ['pptx', 'pptx'],
                default => ['docx', 'docx'],
            };
        }

        return [
            'url' => 'https://docs.google.com/'.$kind.'/d/'.rawurlencode($id).'/export?format='.$format,
            'extension' => $extension,
        ];
    }

    /**
     * @return array{contents: string, mime: string, extension: string}
     */
    protected function downloadGoogleDocExport(string $exportUrl, string $fallbackExtension): array
    {
        $response = $this->httpClient()->get($exportUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Google Docs export failed (HTTP '.$response->status().'). The document may be private.');
        }

        $body = $response->body();
        $detected = MediaType::fromBytes($body, $response->header('Content-Type'));

        if ($detected !== null) {
            return [
                'contents' => $body,
                'mime' => $detected['mime'],
                'extension' => $detected['extension'],
            ];
        }

        // Office documents are ZIP-based; magic bytes may not resolve to a MIME.
        // Trust the export format when the payload is a valid ZIP (PK header).
        if (str_starts_with($body, "PK\x03\x04") || str_starts_with($body, "PK\x05\x06")) {
            return [
                'contents' => $body,
                'mime' => match ($fallbackExtension) {
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    default => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                },
                'extension' => $fallbackExtension,
            ];
        }

        throw new RuntimeException('Google Docs export did not return a supported file (the document may be private or shared without access).');
    }

    /**
     * @return array{contents: string, mime: string, extension: string}
     */
    protected function downloadDriveFile(string $fileId): array
    {
        $client = $this->httpClient();
        $exportUrl = 'https://drive.google.com/uc?export=download&id='.rawurlencode($fileId);

        $response = $client->get($exportUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Google Drive download failed (HTTP '.$response->status().').');
        }

        $body = $response->body();
        $detected = MediaType::fromBytes($body, $response->header('Content-Type'));

        if ($detected !== null) {
            return [
                'contents' => $body,
                'mime' => $detected['mime'],
                'extension' => $detected['extension'],
            ];
        }

        $confirm = $this->extractConfirmToken($body);

        if ($confirm === null) {
            throw new RuntimeException('Could not download file from Google Drive (file may be private or blocked).');
        }

        $confirmedUrl = 'https://drive.google.com/uc?export=download&confirm='.rawurlencode($confirm)
            .'&id='.rawurlencode($fileId);

        $confirmed = $client->withHeaders([
            'Cookie' => $this->cookieHeaderFromResponse($response),
        ])->get($confirmedUrl);

        if (! $confirmed->successful()) {
            throw new RuntimeException('Google Drive confirmed download failed (HTTP '.$confirmed->status().').');
        }

        $fileBody = $confirmed->body();
        $detected = MediaType::fromBytes($fileBody, $confirmed->header('Content-Type'));

        if ($detected === null) {
            throw new RuntimeException('Google Drive response was not a supported PDF, image, or Word file.');
        }

        return [
            'contents' => $fileBody,
            'mime' => $detected['mime'],
            'extension' => $detected['extension'],
        ];
    }

    /**
     * @return array{contents: string, mime: string, extension: string}
     */
    protected function downloadDirect(string $url): array
    {
        $response = $this->httpClient()->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('File download failed (HTTP '.$response->status().').');
        }

        $body = $response->body();
        $detected = MediaType::fromBytes($body, $response->header('Content-Type'));

        if ($detected === null) {
            throw new RuntimeException('Downloaded content is not a supported PDF, image, or Word file.');
        }

        return [
            'contents' => $body,
            'mime' => $detected['mime'],
            'extension' => $detected['extension'],
        ];
    }

    protected function httpClient(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; SPLIS-OrdinancePdfMirror/1.0)',
            'Accept' => '*/*',
        ])
            ->timeout(120)
            ->withOptions([
                'allow_redirects' => true,
            ]);
    }

    protected function extractConfirmToken(string $html): ?string
    {
        if (preg_match('/confirm=([0-9A-Za-z_-]+)/', $html, $matches)) {
            return $matches[1];
        }

        if (preg_match('/name="confirm"\s+value="([^"]+)"/', $html, $matches)) {
            return $matches[1];
        }

        if (preg_match("/name='confirm'\\s+value='([^']+)'/", $html, $matches)) {
            return $matches[1];
        }

        if (preg_match('/id="download-form"[^>]*action="[^"]*confirm=([0-9A-Za-z_-]+)/', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function cookieHeaderFromResponse(\Illuminate\Http\Client\Response $response): string
    {
        $raw = $response->header('Set-Cookie');

        if (! is_string($raw) || $raw === '') {
            return '';
        }

        $parts = [];
        foreach (preg_split('/,(?=[^;]+?=)/', $raw) ?: [] as $cookie) {
            $pair = trim(explode(';', $cookie, 2)[0]);
            if ($pair !== '') {
                $parts[] = $pair;
            }
        }

        return implode('; ', $parts);
    }
}
