<?php

namespace App\Support;

final class MediaType
{
    /**
     * @return array{mime: string, extension: string}|null
     */
    public static function fromBytes(string $bytes, ?string $contentType = null): ?array
    {
        if (str_starts_with($bytes, '%PDF')) {
            return ['mime' => 'application/pdf', 'extension' => 'pdf'];
        }

        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return ['mime' => 'image/jpeg', 'extension' => 'jpg'];
        }

        if (str_starts_with($bytes, "\x89PNG\r\n\x1A\n")) {
            return ['mime' => 'image/png', 'extension' => 'png'];
        }

        if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
            return ['mime' => 'image/gif', 'extension' => 'gif'];
        }

        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return ['mime' => 'image/webp', 'extension' => 'webp'];
        }

        $type = strtolower((string) $contentType);

        if (str_contains($type, 'application/pdf')) {
            return ['mime' => 'application/pdf', 'extension' => 'pdf'];
        }

        if (str_contains($type, 'image/jpeg')) {
            return ['mime' => 'image/jpeg', 'extension' => 'jpg'];
        }

        if (str_contains($type, 'image/png')) {
            return ['mime' => 'image/png', 'extension' => 'png'];
        }

        if (str_contains($type, 'image/gif')) {
            return ['mime' => 'image/gif', 'extension' => 'gif'];
        }

        if (str_contains($type, 'image/webp')) {
            return ['mime' => 'image/webp', 'extension' => 'webp'];
        }

        if (str_contains($type, 'wordprocessingml.document') || str_contains($type, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')) {
            return [
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'extension' => 'docx',
            ];
        }

        if (str_contains($type, 'application/msword')) {
            return ['mime' => 'application/msword', 'extension' => 'doc'];
        }

        // DOCX/DOC are ZIP-based; defer to extension/content-type callers when only magic is present.
        return null;
    }

    /**
     * @return array{mime: string, extension: string}|null
     */
    public static function fromPath(string $absolutePath): ?array
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $bytes = (string) file_get_contents($absolutePath, false, null, 0, 32);
        $detected = self::fromBytes($bytes);

        if ($detected !== null) {
            return $detected;
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => ['mime' => 'application/pdf', 'extension' => 'pdf'],
            'jpg', 'jpeg' => ['mime' => 'image/jpeg', 'extension' => 'jpg'],
            'png' => ['mime' => 'image/png', 'extension' => 'png'],
            'gif' => ['mime' => 'image/gif', 'extension' => 'gif'],
            'webp' => ['mime' => 'image/webp', 'extension' => 'webp'],
            'docx' => [
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'extension' => 'docx',
            ],
            'doc' => ['mime' => 'application/msword', 'extension' => 'doc'],
            default => null,
        };
    }

    public static function isImageMime(string $mime): bool
    {
        return str_starts_with(strtolower($mime), 'image/');
    }

    public static function isOfficeMime(string $mime): bool
    {
        $mime = strtolower($mime);

        return $mime === 'application/msword'
            || $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            || str_contains($mime, 'wordprocessingml');
    }

    public static function isPdfMime(string $mime): bool
    {
        return strtolower($mime) === 'application/pdf';
    }

    /**
     * @return array{mime: string, extension: string}
     */
    public static function fromUploadedMime(string $mime, ?string $originalExtension = null): array
    {
        $mime = strtolower($mime);
        $originalExtension = strtolower(ltrim((string) $originalExtension, '.'));

        if ($mime === 'application/pdf' || $originalExtension === 'pdf') {
            return ['mime' => 'application/pdf', 'extension' => 'pdf'];
        }

        if (
            str_contains($mime, 'wordprocessingml.document')
            || $originalExtension === 'docx'
        ) {
            return [
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'extension' => 'docx',
            ];
        }

        if ($mime === 'application/msword' || $originalExtension === 'doc') {
            return ['mime' => 'application/msword', 'extension' => 'doc'];
        }

        if ($mime === 'image/jpeg' || in_array($originalExtension, ['jpg', 'jpeg'], true)) {
            return ['mime' => 'image/jpeg', 'extension' => 'jpg'];
        }

        if ($mime === 'image/png' || $originalExtension === 'png') {
            return ['mime' => 'image/png', 'extension' => 'png'];
        }

        if ($mime === 'image/gif' || $originalExtension === 'gif') {
            return ['mime' => 'image/gif', 'extension' => 'gif'];
        }

        if ($mime === 'image/webp' || $originalExtension === 'webp') {
            return ['mime' => 'image/webp', 'extension' => 'webp'];
        }

        return ['mime' => 'application/pdf', 'extension' => 'pdf'];
    }
}
