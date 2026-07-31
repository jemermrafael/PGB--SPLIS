<?php

namespace Tests\Feature;

use App\Enums\ObBlockType;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Models\User;
use App\Services\ObDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObCommitteeReportSectionPlacementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ObDocument $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $session = LegislativeSession::query()->create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $this->document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB',
            'status' => ObDocument::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);

        $sections = [
            ['I.', 'ROLL CALL'],
            ['II.', 'APPEARANCE OF GUEST/S'],
            ['III.', ''],
            ['IV.', 'COMMITTEE REPORT'],
            ['V.', 'PRIVILEGE HOUR'],
            ['VI.', 'CALENDAR OF BUSINESS'],
        ];

        foreach ($sections as $index => [$numeral, $title]) {
            $this->block(ObBlockType::RomanSection, $index + 1, [
                'numeral' => $numeral,
                'title' => $title,
            ]);
        }

        $this->block(ObBlockType::SubsectionLabel, 7, ['text' => 'A. UNFINISHED BUSINESS']);
        $this->block(ObBlockType::SubsectionLabel, 8, ['text' => 'B. BUSINESS FOR THE DAY']);
        $this->block(ObBlockType::RomanSection, 9, ['numeral' => 'VII.', 'title' => 'ANNOUNCEMENTS']);
        $this->block(ObBlockType::Adjournment, 10, []);
    }

    public function test_it_pulls_stray_committee_report_rows_back_under_section_four(): void
    {
        // Rows appended past the closing print an empty IV plus a stray table.
        $stray = $this->reportBlock('332', 11);
        $other = $this->reportBlock('311', 12);

        app(ObDocumentService::class)->normalizeCommitteeReportSection($this->document);

        $this->assertSame(
            ['311', '332'],
            $this->orderedAgendaNosInCommitteeReportZone(),
        );

        $this->assertSame(1, (int) $other->refresh()->content['row_no']);
        $this->assertSame(2, (int) $stray->refresh()->content['row_no']);
    }

    public function test_added_committee_report_row_lands_in_section_four_without_a_reference_block(): void
    {
        $block = app(ObDocumentService::class)->addBlock(
            $this->document,
            ObBlockType::CommitteeReport,
        );

        $numerals = ObBlock::query()
            ->where('ob_document_id', $this->document->id)
            ->orderBy('sort_order')
            ->get()
            ->takeWhile(fn (ObBlock $candidate) => $candidate->id !== $block->id)
            ->filter(fn (ObBlock $candidate) => $candidate->type === ObBlockType::RomanSection)
            ->map(fn (ObBlock $candidate) => (string) $candidate->content['numeral'])
            ->values()
            ->all();

        $this->assertSame(['I.', 'II.', 'III.', 'IV.'], $numerals, 'the new row must sit inside IV, before V. Privilege Hour');
    }

    /**
     * @return list<string>
     */
    protected function orderedAgendaNosInCommitteeReportZone(): array
    {
        $blocks = ObBlock::query()
            ->where('ob_document_id', $this->document->id)
            ->orderBy('sort_order')
            ->get();

        $start = $blocks->search(fn (ObBlock $block) => ($block->content['numeral'] ?? '') === 'IV.');
        $end = $blocks->search(fn (ObBlock $block) => ($block->content['numeral'] ?? '') === 'V.');

        return $blocks
            ->slice($start + 1, $end - $start - 1)
            ->filter(fn (ObBlock $block) => $block->type === ObBlockType::CommitteeReport)
            ->map(fn (ObBlock $block) => (string) $block->content['agenda_no'])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function block(ObBlockType $type, int $sortOrder, array $content): ObBlock
    {
        return ObBlock::query()->create([
            'ob_document_id' => $this->document->id,
            'type' => $type,
            'sort_order' => $sortOrder,
            'content' => $content,
        ]);
    }

    protected function reportBlock(string $trackingNo, int $sortOrder): ObBlock
    {
        $agenda = AgendaItem::query()->create([
            'tracking_no' => $trackingNo,
            'title' => 'Agenda '.$trackingNo,
            'committee_referred' => 'Housing and Land Use',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $this->user->id,
        ]);

        return ObBlock::query()->create([
            'ob_document_id' => $this->document->id,
            'type' => ObBlockType::CommitteeReport,
            'sort_order' => $sortOrder,
            'agenda_item_id' => $agenda->id,
            'content' => [
                'committee_id' => 21,
                'committee_name' => 'SP Committee on Housing and Land Use',
                'chair_name' => 'Board Member Chair',
                'row_no' => 9,
                'agenda_no' => $trackingNo,
                'agenda_item_ids' => [$agenda->id],
            ],
        ]);
    }
}
