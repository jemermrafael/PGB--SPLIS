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
            default => null,
        };
    }

    public static function isImageMime(string $mime): bool
    {
        return str_starts_with(strtolower($mime), 'image/');
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
