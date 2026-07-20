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
     * Download a PDF or image from Google Drive / direct URL.
     *
     * @return array{contents: string, mime: string, extension: string}
     *
     * @throws RuntimeException
     */
    public function downloadFile(string $url): array
    {
        $url = trim($url);

        if ($url === '') {
            throw new RuntimeException('File URL is empty.');
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
            throw new RuntimeException('Google Drive response was not a supported PDF or image file.');
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
            throw new RuntimeException('Downloaded content is not a supported PDF or image file.');
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
