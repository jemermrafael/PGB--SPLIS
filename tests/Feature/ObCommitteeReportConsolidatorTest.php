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
use App\Services\AgendaObPlacementService;
use App\Services\ObCommitteeReportConsolidator;
use App\Support\ObAgendaSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

        // Row numbers follow lowest agenda number after normalize.
        $this->assertSame(
            [1, 2],
            [(int) $first->content['row_no'], (int) $other->refresh()->content['row_no']],
            'housing(324+) should be row 1, transport(332) row 2; got housing='.json_encode($first->content).' transport='.json_encode($other->fresh()->content)
        );

        // Agendas from the removed blocks now point at the surviving block.
        foreach (['324', '325'] as $absorbedNo) {
            $this->assertDatabaseHas('agenda_ob_placements', [
                'agenda_item_id' => $housing['agendas'][$absorbedNo]->id,
                'ob_block_id' => $first->id,
                'section' => 'committee_reports',
            ]);
        }
    }

    public function test_it_folds_separate_reports_from_the_same_committee_into_one_row(): void
    {
        $first = $this->report('Housing and Land Use', ['324']);
        $second = $this->report('Housing and Land Use', ['325']);

        $primary = $this->block($first['agendas']['324'], 'SP Committee on Housing and Land Use', 21, 1);
        $other = $this->block($second['agendas']['325'], 'SP Committee on Housing and Land Use', 21, 2);

        $this->assertSame(1, app(ObCommitteeReportConsolidator::class)->consolidate($this->document));
        $this->assertNull(ObBlock::query()->find($other->id));

        $primary->refresh();
        $this->assertSame(['324', '325'], ObAgendaSnapshot::agendaNosFromContent($primary->content));
        $this->assertEqualsCanonicalizing(
            [
                $first['agendas']['324']->id,
                $second['agendas']['325']->id,
            ],
            $primary->content['agenda_item_ids'],
        );
    }

    public function test_it_folds_same_committee_blocks_even_without_a_filed_report(): void
    {
        $agenda = $this->agenda('401', 'Housing and Land Use');
        $other = $this->agenda('402', 'Housing and Land Use');

        $primary = $this->block($agenda, 'SP Committee on Housing and Land Use', 21, 1);
        $redundant = $this->block($other, 'SP Committee on Housing and Land Use', 21, 2);

        $this->assertSame(1, app(ObCommitteeReportConsolidator::class)->consolidate($this->document));
        $this->assertNull(ObBlock::query()->find($redundant->id));
        $primary->refresh();
        $this->assertSame(['401', '402'], ObAgendaSnapshot::agendaNosFromContent($primary->content));
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

    public function test_absorb_re_records_every_merged_agenda_before_deleting_redundant_block(): void
    {
        Storage::fake('local');

        $boardMember = BoardMember::query()->create([
            'name' => 'Housing Chair',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);

        $agendas = collect(['334', '324', '325'])->mapWithKeys(function (string $no) {
            return [$no => $this->agenda($no, 'Housing and Land Use')];
        });

        $path = 'board-member-committee-reports/'.$boardMember->id.'/shared.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4');

        $report = BoardMemberCommitteeReport::query()->create([
            'board_member_id' => $boardMember->id,
            'title' => 'Housing report',
            'pdf_path' => $path,
            'original_filename' => 'housing.pdf',
            'previous_ob_placements' => [],
            'submitted_by' => $this->user->id,
            'submitted_at' => now(),
        ]);
        $report->agendaItems()->sync($agendas->pluck('id')->all());

        $committee = [
            'committee_id' => 21,
            'committee_name' => 'SP Committee on Housing and Land Use',
            'chair_name' => 'Board Member Housing Chair',
        ];

        $original = ObBlock::query()->create([
            'ob_document_id' => $this->document->id,
            'type' => ObBlockType::CommitteeReport,
            'sort_order' => 20,
            'agenda_item_id' => $agendas['334']->id,
            'content' => array_merge($committee, [
                'row_no' => 1,
                'agenda_no' => '334',
                'agenda_item_ids' => $agendas->pluck('id')->all(),
            ]),
        ]);

        foreach ($agendas as $agenda) {
            app(AgendaObPlacementService::class)->record($agenda, $original, $this->document, 'committee_reports');
        }

        $fragment = ObBlock::query()->create([
            'ob_document_id' => $this->document->id,
            'type' => ObBlockType::CommitteeReport,
            'sort_order' => 10,
            'agenda_item_id' => $agendas['324']->id,
            'content' => array_merge($committee, [
                'row_no' => 2,
                'agenda_no' => '324',
                'agenda_nos' => ['324', '325'],
                'agenda_item_ids' => [$agendas['324']->id, $agendas['325']->id],
            ]),
        ]);

        $original->update([
            'content' => array_merge($committee, [
                'row_no' => 1,
                'agenda_no' => '334',
                'agenda_item_ids' => [$agendas['334']->id],
            ]),
        ]);

        app(ObCommitteeReportConsolidator::class)->consolidate($this->document);

        $this->assertNull(ObBlock::query()->find($original->id));
        $survivor = ObBlock::query()->findOrFail($fragment->id);
        $this->assertEqualsCanonicalizing(
            $agendas->pluck('id')->all(),
            $survivor->content['agenda_item_ids'],
        );

        foreach ($agendas as $agenda) {
            $this->assertDatabaseHas('agenda_ob_placements', [
                'agenda_item_id' => $agenda->id,
                'ob_block_id' => $survivor->id,
                'section' => 'committee_reports',
            ]);
        }
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
