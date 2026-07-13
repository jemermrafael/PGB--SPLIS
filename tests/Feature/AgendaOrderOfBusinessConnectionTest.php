<?php

namespace Tests\Feature;

use App\Enums\ObBlockType;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\AgendaObPlacement;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Models\User;
use App\Services\ObDocumentTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaOrderOfBusinessConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_placed_agenda_cannot_be_added_again_from_connections(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Encoder,
            'is_active' => true,
        ]);

        [$agenda, $session] = $this->seedAgendaOnSession($user);

        $this->assertFalse($user->can('addToOrderOfBusiness', $agenda));
        $this->assertTrue($user->can('removeFromOrderOfBusiness', $agenda));

        $this->actingAs($user)
            ->post(route('agenda.add-to-order-of-business', $agenda), [
                'legislative_session_id' => $session->id,
                'agenda_section' => 'unassigned_regular',
            ])
            ->assertForbidden();
    }

    public function test_encoder_can_remove_agenda_from_order_of_business(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Encoder,
            'is_active' => true,
        ]);

        [$agenda, $session, $document] = $this->seedAgendaOnSession($user);

        $this->actingAs($user)
            ->post(route('agenda.remove-from-order-of-business', $agenda), [
                'legislative_session_id' => $session->id,
            ])
            ->assertRedirect(route('agenda.show', $agenda));

        $this->assertFalse(
            ObBlock::query()
                ->where('ob_document_id', $document->id)
                ->where('agenda_item_id', $agenda->id)
                ->exists()
        );
        $this->assertFalse(
            AgendaObPlacement::query()
                ->where('agenda_item_id', $agenda->id)
                ->where('legislative_session_id', $session->id)
                ->exists()
        );

        $agenda->refresh();
        $this->assertNotNull($agenda->ob_manual_override_at);
        $this->assertTrue($user->can('addToOrderOfBusiness', $agenda));
    }

    /**
     * @return array{0: AgendaItem, 1: LegislativeSession, 2: ObDocument}
     */
    protected function seedAgendaOnSession(User $user): array
    {
        $agenda = AgendaItem::create([
            'title' => 'Placed agenda',
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

        $block = ObBlock::create([
            'ob_document_id' => $document->id,
            'type' => ObBlockType::UnassignedAgenda,
            'sort_order' => 99,
            'agenda_item_id' => $agenda->id,
            'content' => [
                'title' => $agenda->title,
                'session_agenda_no' => '1',
            ],
        ]);

        AgendaObPlacement::create([
            'agenda_item_id' => $agenda->id,
            'ob_block_id' => $block->id,
            'legislative_session_id' => $session->id,
            'ob_document_id' => $document->id,
            'section' => 'unassigned_regular',
            'section_label' => '2. Regular unassigned business',
            'session_agenda_no' => '1',
            'placed_by' => $user->id,
        ]);

        return [$agenda->fresh(), $session, $document];
    }
}
