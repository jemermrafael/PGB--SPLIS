<?php

namespace Tests\Unit;

use App\Support\ObTitleMarkup;
use PHPUnit\Framework\TestCase;

class ObTitleMarkupTest extends TestCase
{
    public function test_it_keeps_only_bold_highlight_and_line_break_markup(): void
    {
        $html = '<strong onclick="alert(1)">Bold</strong> <mark class="x">highlight</mark> <em>plain</em><br class="x"><script>alert(1)</script>';

        $this->assertSame(
            '<strong>Bold</strong> <mark>highlight</mark> plain<br>alert(1)',
            ObTitleMarkup::sanitize($html),
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

        $this->assertNull(ObTitleMarkup::forTitle('<strong>Old title</strong>', 'New title'));
    }
}
