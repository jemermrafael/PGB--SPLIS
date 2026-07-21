<?php

namespace Tests\Unit;

use App\Enums\ObBlockType;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Services\ObSectionThreeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObSectionThreeSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refreshes_section_three_when_held_on_date_does_not_match_prior_session(): void
    {
        $prior = LegislativeSession::query()->create([
            'session_date' => '2026-07-20',
            'session_number' => '26TH REGULAR SESSION',
            'session_kind' => 'regular',
            'venue' => 'AT THE SESSION HALL, BALANGA CITY',
            'status' => 'draft',
        ]);

        $session = LegislativeSession::query()->create([
            'session_date' => '2026-07-27',
            'session_number' => '27TH REGULAR SESSION',
            'session_kind' => 'regular',
            'prior_session_id' => $prior->id,
            'status' => 'draft',
        ]);

        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'Order of Business',
            'status' => 'draft',
        ]);

        ObBlock::query()->create([
            'ob_document_id' => $document->id,
            'type' => ObBlockType::RomanSection,
            'sort_order' => 1,
            'content' => [
                'numeral' => 'III.',
                'title' => '',
                'body' => 'READING AND APPROVAL OF THE JOURNAL OF PROCEEDINGS & MINUTES OF THE 26TH REGULAR SESSION HELD ON JULY 27, 2026 AT AT THE SESSION HALL, BALANGA CITY',
            ],
        ]);

        $synced = app(ObSectionThreeSyncService::class)->syncForSession($session->fresh(['priorSession', 'obDocument.blocks']));

        $this->assertTrue($synced);

        $body = $document->fresh()->blocks()->first()->content['body'] ?? '';

        $this->assertStringContainsString('HELD ON JULY 20, 2026', $body);
        $this->assertStringNotContainsString('HELD ON JULY 27, 2026', $body);
        $this->assertStringNotContainsString('AT AT', $body);
    }
}
