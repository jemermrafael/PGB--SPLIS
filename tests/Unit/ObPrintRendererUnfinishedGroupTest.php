<?php

namespace Tests\Unit;

use App\Enums\ObBlockType;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Models\User;
use App\Services\ObPrintRenderer;
use App\Support\ObAgendaSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObPrintRendererUnfinishedGroupTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ObDocument $document;

    protected LegislativeSession $session;

    protected int $sortOrder = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->session = LegislativeSession::query()->create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'session_number' => '54th',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $this->document = ObDocument::query()->create([
            'legislative_session_id' => $this->session->id,
            'title' => 'OB',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_scattered_committee_blocks_collapse_into_one_group(): void
    {
        $this->committeeHeader('SP COMMITTEE ON PEACE AND ORDER AND PUBLIC SAFETY');
        $this->unfinishedAgenda('220', 'SP COMMITTEE ON PEACE AND ORDER AND PUBLIC SAFETY');
        $this->committeeHeader('SP COMMITTEE ON HEALTH AND SANITATION');
        $this->unfinishedAgenda('188', 'SP COMMITTEE ON HEALTH AND SANITATION');
        // No header of its own: the renderer opens a second Peace and Order group.
        $this->unfinishedAgenda('221', 'SP COMMITTEE ON PEACE AND ORDER AND PUBLIC SAFETY');
        $this->committeeHeader('SP COMMITTEE ON PEACE AND ORDER AND PUBLIC SAFETY');
        $this->unfinishedAgenda('322', 'SP COMMITTEE ON PEACE AND ORDER AND PUBLIC SAFETY');

        $this->assertSame([
            'SP COMMITTEE ON PEACE AND ORDER AND PUBLIC SAFETY' => ['220', '221', '322'],
            'SP COMMITTEE ON HEALTH AND SANITATION' => ['188'],
        ], $this->renderedGroups());
    }

    public function test_groups_never_merge_across_a_section_divider(): void
    {
        $this->committeeHeader('SP COMMITTEE ON HEALTH AND SANITATION');
        $this->unfinishedAgenda('188', 'SP COMMITTEE ON HEALTH AND SANITATION');

        ObBlock::query()->create([
            'ob_document_id' => $this->document->id,
            'type' => ObBlockType::RomanSection,
            'sort_order' => ++$this->sortOrder,
            'content' => ['numeral' => 'VIII', 'title' => 'OTHER MATTERS'],
        ]);

        $this->unfinishedAgenda('335', 'SP COMMITTEE ON HEALTH AND SANITATION');

        $groups = collect(app(ObPrintRenderer::class)->segments(
            $this->document->blocks()->with('agendaItem')->orderBy('sort_order')->get(),
            $this->session,
        ))->where('type', 'unfinished_group')->values();

        $this->assertCount(2, $groups);
        $this->assertSame(['188'], $this->agendaNos($groups[0]));
        $this->assertSame(['335'], $this->agendaNos($groups[1]));
    }

    protected function committeeHeader(string $name): void
    {
        ObBlock::query()->create([
            'ob_document_id' => $this->document->id,
            'type' => ObBlockType::UnfinishedCommittee,
            'sort_order' => ++$this->sortOrder,
            'content' => ['committee_name' => $name, 'chair_name' => 'Board Member Chair'],
        ]);
    }

    protected function unfinishedAgenda(string $agendaNo, string $committee): void
    {
        ObBlock::query()->create([
            'ob_document_id' => $this->document->id,
            'type' => ObBlockType::UnfinishedAgenda,
            'sort_order' => ++$this->sortOrder,
            'content' => [
                'agenda_no' => $agendaNo,
                'committee_name' => $committee,
                'title' => 'Agenda '.$agendaNo,
            ],
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function renderedGroups(): array
    {
        $groups = [];

        foreach (app(ObPrintRenderer::class)->segments(
            $this->document->blocks()->with('agendaItem')->orderBy('sort_order')->get(),
            $this->session,
        ) as $segment) {
            if (($segment['type'] ?? '') === 'unfinished_group') {
                $groups[$segment['committee_name']] = $this->agendaNos($segment);
            }
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $segment
     * @return list<string>
     */
    protected function agendaNos(array $segment): array
    {
        return array_map(
            fn (array $item) => ObAgendaSnapshot::displayAgendaNo($item),
            $segment['items'],
        );
    }
}
