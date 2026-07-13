<?php

namespace Tests\Unit;

use App\Support\PdfEmbedUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PdfEmbedUrlTest extends TestCase
{
    public function test_null_and_blank_return_null(): void
    {
        $this->assertNull(PdfEmbedUrl::forIframe(null));
        $this->assertNull(PdfEmbedUrl::forIframe(''));
        $this->assertNull(PdfEmbedUrl::forIframe('   '));
    }

    #[DataProvider('driveUrls')]
    public function test_converts_google_drive_links_to_preview(string $input, string $expected): void
    {
        $this->assertSame($expected, PdfEmbedUrl::forIframe($input));
    }

    public static function driveUrls(): array
    {
        return [
            'view sharing' => [
                'https://drive.google.com/file/d/1eD0QYwNGglMHyG8IOfFMwjBgWZZhTRrq/view?usp=sharing',
                'https://drive.google.com/file/d/1eD0QYwNGglMHyG8IOfFMwjBgWZZhTRrq/preview',
            ],
            'open id' => [
                'https://drive.google.com/open?id=abc123',
                'https://drive.google.com/file/d/abc123/preview',
            ],
            'uc id' => [
                'https://drive.google.com/uc?id=xyz&export=download',
                'https://drive.google.com/file/d/xyz/preview',
            ],
        ];
    }

    public function test_passthrough_for_other_urls(): void
    {
        $url = 'https://example.com/docs/ordinance.pdf';
        $this->assertSame($url, PdfEmbedUrl::forIframe($url));
    }
}
