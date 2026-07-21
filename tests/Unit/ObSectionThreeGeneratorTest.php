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
            'venue' => 'AT THE SESSION HALL, BALANGA CITY',
        ]);
        $session = new LegislativeSession();
        $session->setRelation('priorSession', $prior);

        $body = (new ObSectionThreeGenerator)->bodyForSession($session);

        $this->assertStringContainsString(
            'HELD ON JULY 27, 2026 AT THE SESSION HALL, BALANGA CITY',
            $body,
        );
        $this->assertStringNotContainsString('AT AT', $body);
    }

    public function test_it_highlights_the_prior_session_details_in_linked_body_html(): void
    {
        $prior = new LegislativeSession([
            'session_number' => '26TH REGULAR SESSION',
            'session_date' => '2026-07-27',
            'venue' => 'AT THE SESSION HALL, BALANGA CITY',
        ]);
        $session = new LegislativeSession();
        $session->setRelation('priorSession', $prior);

        $html = (new ObSectionThreeGenerator)->linkedBodyHtml($session, null, [
            'journal_url' => 'https://example.test/journal.pdf',
            'minutes_url' => 'https://example.test/minutes.pdf',
        ]);

        $this->assertStringContainsString(
            '<span class="ob-print-section-three-highlight">26TH REGULAR SESSION</span>',
            $html,
        );
        $this->assertStringContainsString(
            '<span class="ob-print-section-three-highlight">JULY 27, 2026</span>',
            $html,
        );
        $this->assertStringContainsString(
            '<span class="ob-print-section-three-highlight">THE SESSION HALL, BALANGA CITY</span>',
            $html,
        );
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
            '<span class="ob-print-section-three-highlight">JULY 27, 2026</span>',
            $html,
        );
    }
}
