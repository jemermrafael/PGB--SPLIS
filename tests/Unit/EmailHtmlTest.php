<?php

namespace Tests\Unit;

use App\Support\EmailHtml;
use PHPUnit\Framework\TestCase;

class EmailHtmlTest extends TestCase
{
    public function test_plain_text_is_escaped_with_line_breaks(): void
    {
        $html = EmailHtml::toEmailHtml("Hello <script>alert(1)</script>\nWorld");

        $this->assertStringContainsString('Hello &lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('<br>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_html_formatting_and_images_are_allowed(): void
    {
        $html = EmailHtml::toEmailHtml(
            '<p><strong>Hello</strong></p><img src="https://example.com/logo.png" alt="Logo" width="120" onclick="evil()">'
        );

        $this->assertStringContainsString('<strong>Hello</strong>', $html);
        $this->assertStringContainsString('src="https://example.com/logo.png"', $html);
        $this->assertStringContainsString('alt="Logo"', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }

    public function test_javascript_urls_are_stripped(): void
    {
        $html = EmailHtml::toEmailHtml('<a href="javascript:alert(1)">Click</a><img src="javascript:alert(1)">');

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('href=', $html);
        $this->assertStringNotContainsString('src=', $html);
    }
}
