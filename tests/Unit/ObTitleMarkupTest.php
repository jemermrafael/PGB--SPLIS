<?php

namespace Tests\Unit;

use App\Support\ObTitleMarkup;
use PHPUnit\Framework\TestCase;

class ObTitleMarkupTest extends TestCase
{
    public function test_it_keeps_only_bold_underline_highlight_and_line_break_markup(): void
    {
        $html = '<strong onclick="alert(1)">Bold</strong> <u class="x">under</u> <mark class="x">highlight</mark> <em>plain</em><br class="x"><script>alert(1)</script>';

        $this->assertSame(
            '<strong>Bold</strong> <u>under</u> <mark>highlight</mark> plain<br>alert(1)',
            ObTitleMarkup::sanitize($html),
        );
    }

    public function test_it_normalizes_span_underline_styles_to_u(): void
    {
        $this->assertSame(
            'Road <u>Right-of-Way</u>',
            ObTitleMarkup::sanitize('Road <span style="text-decoration: underline;">Right-of-Way</span>'),
        );
    }

    public function test_it_normalizes_highlight_background_styles_to_mark(): void
    {
        $this->assertSame(
            'TO <mark>APPROVE</mark>',
            ObTitleMarkup::sanitize('TO <span style="background-color: rgb(255, 242, 0);">APPROVE</span>'),
        );
    }

    public function test_it_returns_markup_only_when_it_matches_the_plain_title(): void
    {
        $this->assertSame(
            'Municipal Ordinance <strong><mark>No. 336</mark></strong>',
            ObTitleMarkup::forTitle(
                'Municipal Ordinance <b><mark>No. 336</mark></b>',
                'Municipal Ordinance No. 336',
            ),
        );

        $this->assertSame(
            'Title with <u>underline</u>',
            ObTitleMarkup::forTitle('Title with <u>underline</u>', 'Title with underline'),
        );

        $this->assertNull(ObTitleMarkup::forTitle('<strong>Old title</strong>', 'New title'));
    }
}
