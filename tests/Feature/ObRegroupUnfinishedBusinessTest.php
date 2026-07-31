<?php

namespace Tests\Feature;

use App\Enums\ObBlockType;
use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObRegroupUnfinishedBusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_encoder_can_regroup_fragmented_unfinished_agendas_by_committee(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Encoder,
            'is_active' => true,
        ]);

        $session = LegislativeSession::query()->create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB',
            'status' => ObDocument::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        $this->block($document, ObBlockType::SubsectionLabel, 1, [
            'text' => 'A. UNFINISHED BUSINESS',
        ]);
        $this->block($document, ObBlockType::UnfinishedCommittee, 2, [
            'committee_name' => 'SP COMMITTEE ON PEACE AND ORDER AND PUBLIC SAFETY',
        ]);
        $this->block($document, ObBlockType::UnfinishedAgenda, 3, [
            'agenda_no' => '220',
            'committee_id' => 13,
            'committee_name' => 'SP COMMITTEE ON PEACE AND ORDER AND PUBLIC SAFETY',
        ]);
        $this->block($document, ObBlockType::UnfinishedCommittee, 4, [
            'committee_name' => 'SP COMMITTEE ON HEALTH AND SANITATION',
        ]);
        $this->block($document, ObBlockType::UnfinishedAgenda, 5, [
            'agenda_no' => '188',
            'committee_id' => 17,
            'committee_name' => 'SP COMMITTEE ON HEALTH AND SANITATION',
        ]);
        $this->block($document, ObBlockType::UnfinishedCommittee, 6, [
            'committee_name' => 'SP COMMITTEE ON PEACE AND ORDER AND PUBLIC SAFETY',
        ]);
        $this->block($document, ObBlockType::UnfinishedAgenda, 7, [
            'agenda_no' => '323',
            'committee_id' => 13,
            'committee_name' => 'SP COMMITTEE ON PEACE AND ORDER AND PUBLIC SAFETY',
        ]);
        $this->block($document, ObBlockType::SubsectionLabel, 8, [
            'text' => 'B. BUSINESS FOR THE DAY',
        ]);

        $this->actingAs($user)
            ->get(route('ob.document.maker', $session))
            ->assertOk()
            ->assertSee('Regroup Unfinished Business');

        $response = $this->actingAs($user)
            ->postJson(route('ob.document.regroup-unfinished', $session));

        $response->assertOk()
            ->assertJsonStructure(['blocks', 'document']);

        $unfinished = ObBlock::query()
            ->where('ob_document_id', $document->id)
            ->whereIn('type', [
                ObBlockType::UnfinishedCommittee,
                ObBlockType::UnfinishedAgenda,
            ])
            ->orderBy('sort_order')
            ->get();

        $this->assertSame([
            'committee:SP COMMITTEE ON HEALTH AND SANITATION',
            'agenda:188',
            'committee:SP COMMITTEE ON PEACE AND ORDER AND PUBLIC SAFETY',
            'agenda:220',
            'agenda:323',
        ], $unfinished->map(function (ObBlock $block): string {
            if ($block->type === ObBlockType::UnfinishedCommittee) {
                return 'committee:'.$block->content['committee_name'];
            }

            return 'agenda:'.$block->content['agenda_no'];
        })->all());
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function block(
        ObDocument $document,
        ObBlockType $type,
        int $sortOrder,
        array $content,
    ): ObBlock {
        return ObBlock::query()->create([
            'ob_document_id' => $document->id,
            'type' => $type,
            'sort_order' => $sortOrder,
            'content' => $content,
        ]);
    }
}
