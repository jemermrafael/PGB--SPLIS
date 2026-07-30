<?php

namespace Tests\Feature;

use App\Enums\ObBlockType;
use App\Models\AgendaItem;
use App\Models\BoardMember;
use App\Models\BoardMemberCommitteeReport;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Models\User;
use App\Services\ObCommitteeReportConsolidator;
use App\Support\ObAgendaSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObCommitteeReportConsolidatorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ObDocument $document;

    protected int $sortOrder = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $session = LegislativeSession::query()->create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'session_number' => '54th',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $this->document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_it_folds_scattered_blocks_of_one_report_into_a_single_row(): void
    {
        $housing = $this->report('Housing and Land Use', ['334', '324', '325']);
        $transport = $this->report('Transportation and Communication', ['332']);

        // Interleaved on purpose: the housing blocks are not next to each other.
        $first = $this->block($housing['agendas']['334'], 'SP Committee on Housing and Land Use', 21, 1);
        $other = $this->block($transport['agendas']['332'], 'SP Committee on Transportation and Communication', 14, 2);
        $second = $this->block($housing['agendas']['324'], 'SP Committee on Housing and Land Use', 21, 3);
        $third = $this->block($housing['agendas']['325'], 'SP Committee on Housing and Land Use', 21, 4);

        $absorbed = app(ObCommitteeReportConsolidator::class)->consolidate($this->document);

        $this->assertSame(2, $absorbed);
        $this->assertNull(ObBlock::query()->find($second->id));
        $this->assertNull(ObBlock::query()->find($third->id));

        $first->refresh();
        $this->assertSame(['324', '325', '334'], ObAgendaSnapshot::agendaNosFromContent($first->content));
        $this->assertEqualsCanonicalizing(
            $housing['agendas']->pluck('id')->all(),
            $first->content['agenda_item_ids'],
        );

        // Row numbers close the gap left by the removed blocks.
        $this->assertSame(1, $first->content['row_no']);
        $this->assertSame(2, $other->refresh()->content['row_no']);

        // Agendas from the removed blocks now point at the surviving block.
        foreach (['324', '325'] as $absorbedNo) {
            $this->assertDatabaseHas('agenda_ob_placements', [
                'agenda_item_id' => $housing['agendas'][$absorbedNo]->id,
                'ob_block_id' => $first->id,
                'section' => 'committee_reports',
            ]);
        }
    }

    public function test_it_keeps_separate_reports_from_the_same_committee_apart(): void
    {
        $first = $this->report('Housing and Land Use', ['324']);
        $second = $this->report('Housing and Land Use', ['325']);

        $this->block($first['agendas']['324'], 'SP Committee on Housing and Land Use', 21, 1);
        $this->block($second['agendas']['325'], 'SP Committee on Housing and Land Use', 21, 2);

        $this->assertSame(0, app(ObCommitteeReportConsolidator::class)->consolidate($this->document));
        $this->assertSame(2, ObBlock::query()
            ->where('ob_document_id', $this->document->id)
            ->where('type', ObBlockType::CommitteeReport)
            ->count());
    }

    public function test_it_leaves_blocks_without_a_filed_report_alone(): void
    {
        $agenda = $this->agenda('401', 'Housing and Land Use');
        $other = $this->agenda('402', 'Housing and Land Use');

        $this->block($agenda, 'SP Committee on Housing and Land Use', 21, 1);
        $this->block($other, 'SP Committee on Housing and Land Use', 21, 2);

        $this->assertSame(0, app(ObCommitteeReportConsolidator::class)->consolidate($this->document));
    }

    /**
     * @param  list<string>  $agendaNos
     * @return array{report: BoardMemberCommitteeReport, agendas: \Illuminate\Support\Collection<string, AgendaItem>}
     */
    protected function report(string $committee, array $agendaNos): array
    {
        $boardMember = BoardMember::query()->create([
            'name' => $committee.' Chair',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);

        $agendas = collect($agendaNos)->mapWithKeys(
            fn (string $no) => [$no => $this->agenda($no, $committee)],
        );

        $report = BoardMemberCommitteeReport::query()->create([
            'board_member_id' => $boardMember->id,
            'title' => $committee.' report',
            'pdf_path' => 'board-member-committee-reports/'.$boardMember->id.'/report.pdf',
            'original_filename' => 'report.pdf',
            'previous_ob_placements' => [],
            'submitted_by' => $this->user->id,
            'submitted_at' => now(),
        ]);
        $report->agendaItems()->sync($agendas->pluck('id')->all());

        return ['report' => $report, 'agendas' => $agendas];
    }

    protected function agenda(string $trackingNo, string $committee): AgendaItem
    {
        return AgendaItem::query()->create([
            'tracking_no' => $trackingNo,
            'title' => 'Agenda '.$trackingNo,
            'committee_referred' => $committee,
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $this->user->id,
        ]);
    }

    protected function block(AgendaItem $agenda, string $committeeName, int $committeeId, int $rowNo): ObBlock
    {
        return ObBlock::query()->create([
            'ob_document_id' => $this->document->id,
            'type' => ObBlockType::CommitteeReport,
            'sort_order' => ++$this->sortOrder,
            'agenda_item_id' => $agenda->id,
            'content' => [
                'committee_id' => $committeeId,
                'committee_name' => $committeeName,
                'chair_name' => 'Board Member Chair',
                'row_no' => $rowNo,
                'agenda_no' => $agenda->tracking_no,
                'agenda_item_ids' => [$agenda->id],
            ],
        ]);
    }
}
