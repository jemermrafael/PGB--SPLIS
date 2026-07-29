<?php

namespace Tests\Unit;

use App\Models\LegislativeSession;
use App\Support\ObSectionThreeGenerator;
use Tests\TestCase;

class ObSectionThreeGeneratorTest extends TestCase
{
    public function test_it_removes_a_leading_at_from_the_prior_session_venue(): void
    {
        $prior = new LegislativeSession([
            'session_number' => '26TH REGULAR SESSION',
            'session_date' => '2026-07-27',
            'venue' => 'AT THE SESSION HALL, 6TH FLR., BALANGA CITY',
        ]);
        $session = new LegislativeSession();
        $session->setRelation('priorSession', $prior);

        $body = (new ObSectionThreeGenerator)->bodyForSession($session);

        $this->assertStringContainsString(
            'HELD ON JULY 27, 2026 AT THE SESSION HALL, 6TH FLR., BALANGA CITY',
            $body,
        );
        $this->assertStringNotContainsString('AT AT', $body);
    }

    public function test_it_highlights_the_prior_session_details_in_linked_body_html(): void
    {
        $prior = new LegislativeSession([
            'session_number' => '26TH REGULAR SESSION',
            'session_date' => '2026-07-27',
            'venue' => 'AT THE SESSION HALL, 6TH FLR., BALANGA CITY',
        ]);
        $session = new LegislativeSession();
        $session->setRelation('priorSession', $prior);

        $html = (new ObSectionThreeGenerator)->linkedBodyHtml($session, null, [
            'journal_url' => 'https://example.test/journal.pdf',
            'minutes_url' => 'https://example.test/minutes.pdf',
        ]);

        $this->assertStringContainsString(
            '<span class="ob-print-section-three-highlight">26<sup class="ob-print-ordinal-suffix">TH</sup> REGULAR SESSION</span>',
            $html,
        );
        $this->assertStringContainsString(
            '<span class="ob-print-section-three-highlight">JULY 27, 2026</span>',
            $html,
        );
        $this->assertStringContainsString(
            '<span class="ob-print-section-three-highlight">THE SESSION HALL, 6<sup class="ob-print-ordinal-suffix">TH</sup> FLR., BALANGA CITY</span>',
            $html,
        );
        $this->assertStringContainsString('</span> HELD ON <span', $html);
        $this->assertStringContainsString('</span> AT <span', $html);
        $this->assertStringContainsString('class="ob-print-link"', $html);
        $this->assertStringContainsString('>JOURNAL</a>', $html);
        $this->assertStringContainsString('>MINUTES</a>', $html);
    }

    public function test_it_highlights_held_on_date_from_body_text(): void
    {
        $session = new LegislativeSession();
        $session->setRelation('priorSession', null);

        $html = (new ObSectionThreeGenerator)->linkedBodyHtml(
            $session,
            'READING AND APPROVAL OF THE JOURNAL OF PROCEEDINGS & MINUTES OF THE 26TH REGULAR SESSION HELD ON JULY 27, 2026 AT THE SESSION HALL',
        );

        $this->assertStringContainsString(
            '<span class="ob-print-section-three-highlight">26<sup class="ob-print-ordinal-suffix">TH</sup> REGULAR SESSION</span>',
            $html,
        );
        $this->assertStringContainsString(
            '<span class="ob-print-section-three-highlight">JULY 27, 2026</span>',
            $html,
        );
        $this->assertStringContainsString(
            '<span class="ob-print-section-three-highlight">THE SESSION HALL</span>',
            $html,
        );
        $this->assertStringContainsString('HELD ON', $html);
        $this->assertStringNotContainsString(
            '<span class="ob-print-section-three-highlight">HELD ON</span>',
            $html,
        );
        $this->assertStringNotContainsString(
            '>HELD ON JULY',
            $html,
        );
    }

    public function test_it_keeps_held_on_and_at_unhighlighted_across_line_breaks(): void
    {
        $session = new LegislativeSession();
        $session->setRelation('priorSession', null);

        $html = (new ObSectionThreeGenerator)->linkedBodyHtml(
            $session,
            "READING AND APPROVAL OF THE JOURNAL OF PROCEEDINGS & MINUTES OF THE 51ST REGULAR\nSESSION HELD ON JULY 13, 2026 AT THE SESSION HALL, 6TH FLR., THE BUNKER @ THE CAPITOL COMPOUND, TENEJERO, BALANGA CITY, BATAAN 2100",
            [
                'journal_url' => 'https://example.test/journal.pdf',
                'minutes_url' => 'https://example.test/minutes.pdf',
            ],
        );

        $this->assertStringContainsString(
            '<span class="ob-print-section-three-highlight">51<sup class="ob-print-ordinal-suffix">ST</sup> REGULAR'."\n".'SESSION</span>',
            $html,
        );
        $this->assertStringContainsString(
            '<span class="ob-print-section-three-highlight">JULY 13, 2026</span>',
            $html,
        );
        $this->assertStringContainsString(
            '<span class="ob-print-section-three-highlight">THE SESSION HALL, 6<sup class="ob-print-ordinal-suffix">TH</sup> FLR., THE BUNKER @ THE CAPITOL COMPOUND, TENEJERO, BALANGA CITY, BATAAN 2100</span>',
            $html,
        );
        $this->assertStringContainsString('</span> HELD ON <span', $html);
        $this->assertStringContainsString('</span> AT <span', $html);
    }
}
