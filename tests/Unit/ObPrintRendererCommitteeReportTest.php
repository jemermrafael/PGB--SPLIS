<?php

namespace Tests\Unit;

use App\Enums\ObBlockType;
use App\Models\AgendaItem;
use App\Models\BoardMember;
use App\Models\BoardMemberCommitteeReport;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Models\User;
use App\Services\ObPrintRenderer;
use App\Support\ObAgendaSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ObPrintRendererCommitteeReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_non_adjacent_blocks_by_committee_report_and_links_each_agenda_no(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $boardMember = BoardMember::query()->create([
            'name' => 'Housing Chair',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);

        $session = LegislativeSession::query()->create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'session_number' => '54th',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $agendas = collect([
            '334' => AgendaItem::query()->create([
                'tracking_no' => '334',
                'title' => 'Agenda 334',
                'committee_referred' => 'Housing and Land Use',
                'status' => AgendaItem::STATUS_PENDING,
                'prescribed_days' => 0,
                'created_by' => $user->id,
            ]),
            '324' => AgendaItem::query()->create([
                'tracking_no' => '324',
                'title' => 'Agenda 324',
                'committee_referred' => 'Housing and Land Use',
                'status' => AgendaItem::STATUS_PENDING,
                'prescribed_days' => 0,
                'created_by' => $user->id,
            ]),
            '329' => AgendaItem::query()->create([
                'tracking_no' => '329',
                'title' => 'Agenda 329',
                'committee_referred' => 'Housing and Land Use',
                'status' => AgendaItem::STATUS_PENDING,
                'prescribed_days' => 0,
                'created_by' => $user->id,
            ]),
            '325' => AgendaItem::query()->create([
                'tracking_no' => '325',
                'title' => 'Agenda 325',
                'committee_referred' => 'Housing and Land Use',
                'status' => AgendaItem::STATUS_PENDING,
                'prescribed_days' => 0,
                'created_by' => $user->id,
            ]),
        ]);

        $sharedPath = 'board-member-committee-reports/'.$boardMember->id.'/shared.pdf';
        Storage::disk('local')->put($sharedPath, '%PDF-1.4');

        foreach ($agendas as $agenda) {
            $agenda->forceFill([
                'committee_report_pdf_path' => $sharedPath,
            ])->save();
        }

        $report = BoardMemberCommitteeReport::query()->create([
            'board_member_id' => $boardMember->id,
            'title' => 'Housing report',
            'pdf_path' => $sharedPath,
            'original_filename' => 'housing.pdf',
            'previous_ob_placements' => [],
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);
        $report->agendaItems()->sync($agendas->pluck('id')->all());

        $committeeContent = [
            'committee_id' => 21,
            'committee_name' => 'SP Committee on Housing and Land Use',
            'chair_name' => 'Board Member Housing Chair',
        ];

        $blocks = collect([
            ['sort' => 1, 'agenda' => $agendas['334'], 'ids' => [$agendas['334']->id], 'nos' => '334', 'row' => 1],
            ['sort' => 2, 'agenda' => $agendas['324'], 'ids' => [$agendas['324']->id, $agendas['329']->id], 'nos' => ['324', '329'], 'row' => 2],
            ['sort' => 3, 'agenda' => $agendas['325'], 'ids' => [$agendas['325']->id], 'nos' => '325', 'row' => 3],
        ])->map(function (array $row) use ($document, $committeeContent): ObBlock {
            return ObBlock::query()->create([
                'ob_document_id' => $document->id,
                'type' => ObBlockType::CommitteeReport,
                'sort_order' => $row['sort'],
                'agenda_item_id' => $row['agenda']->id,
                'content' => array_merge($committeeContent, [
                    'row_no' => $row['row'],
                    'agenda_no' => is_array($row['nos']) ? $row['nos'][0] : $row['nos'],
                    'agenda_nos' => is_array($row['nos']) ? $row['nos'] : null,
                    'agenda_item_ids' => $row['ids'],
                ]),
            ]);
        });

        $segments = app(ObPrintRenderer::class)->segments($blocks, $session);
        $table = collect($segments)->firstWhere('type', 'committee_reports_table');

        $this->assertNotNull($table);
        $this->assertCount(1, $table['rows']);

        $row = $table['rows'][0];
        $this->assertSame(['324', '325', '329', '334'], ObAgendaSnapshot::agendaNosFromContent($row));
        $this->assertCount(4, $row['agenda_no_links'] ?? []);

        $html = ObAgendaSnapshot::displayAgendaNosLabelHtml($row);
        $this->assertMatchesRegularExpression(
            '#^<a href="[^"]+" class="ob-print-link" target="_blank" rel="noopener">Agenda Nos\. 324, 325, 329, 334</a>$#',
            $html,
        );
        $this->assertStringContainsString(
            '/file/committee_report',
            $html,
        );
    }
}
