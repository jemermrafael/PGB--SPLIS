<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Models\User;
use App\Services\ObDocumentTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObDocumentSyncAgendasTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_agendas_endpoint_places_eligible_items(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Encoder,
            'is_active' => true,
        ]);

        $agenda = AgendaItem::create([
            'title' => 'Eligible for OB',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $session = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $document = ObDocument::create([
            'legislative_session_id' => $session->id,
            'title' => 'OB',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        app(ObDocumentTemplateService::class)->seedDefaultBlocks($document);

        $response = $this->actingAs($user)
            ->postJson(route('ob.document.sync-agendas', $session));

        $response->assertOk()
            ->assertJsonPath('added', 1)
            ->assertJsonPath('relocated', 0);

        $this->assertTrue(
            ObBlock::query()
                ->where('ob_document_id', $document->id)
                ->where('agenda_item_id', $agenda->id)
                ->exists()
        );
    }

    public function test_sync_agendas_rejects_completed_sessions(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Encoder,
            'is_active' => true,
        ]);

        $session = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        ObDocument::create([
            'legislative_session_id' => $session->id,
            'title' => 'OB',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('ob.document.sync-agendas', $session))
            ->assertStatus(422);
    }
}
