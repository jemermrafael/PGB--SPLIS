<?php

namespace App\Support;

final class PdfEmbedUrl
{
    /**
     * Convert a stored PDF URL into one suitable for an iframe embed.
     * Google Drive "view" / "open" links become "/preview".
     */
    public static function forIframe(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (preg_match('#drive\.google\.com/file/d/([^/]+)#i', $url, $matches)) {
            return 'https://drive.google.com/file/d/'.$matches[1].'/preview';
        }

        if (preg_match('#drive\.google\.com/open\?(?:.*&)?id=([^&]+)#i', $url, $matches)) {
            return 'https://drive.google.com/file/d/'.rawurlencode($matches[1]).'/preview';
        }

        if (preg_match('#drive\.google\.com/uc\?(?:.*&)?id=([^&]+)#i', $url, $matches)) {
            return 'https://drive.google.com/file/d/'.rawurlencode($matches[1]).'/preview';
        }

        return $url;
    }
}
