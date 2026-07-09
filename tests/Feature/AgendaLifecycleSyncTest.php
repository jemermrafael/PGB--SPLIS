<?php

namespace Tests\Feature;

use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Models\User;
use App\Services\AgendaLifecycleService;
use App\Services\ObDocumentTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaLifecycleSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_new_session_adds_first_time_agendas_as_unassigned(): void
    {
        $user = User::factory()->create();

        $agenda = AgendaItem::create([
            'title' => 'Test agenda',
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
        app(AgendaLifecycleService::class)->syncNewSession($session, $user->id);

        $agenda->refresh();

        $this->assertSame($session->id, $agenda->last_ob_synced_session_id);
        $this->assertTrue(
            ObBlock::query()
                ->where('ob_document_id', $document->id)
                ->where('agenda_item_id', $agenda->id)
                ->exists()
        );
    }

    public function test_sync_new_session_carries_agendas_to_unfinished_on_later_session(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(AgendaLifecycleService::class);

        $agenda = AgendaItem::create([
            'title' => 'Carry me',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $firstSession = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $firstDocument = ObDocument::create([
            'legislative_session_id' => $firstSession->id,
            'title' => 'OB 1',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        app(ObDocumentTemplateService::class)->seedDefaultBlocks($firstDocument);
        $lifecycle->syncNewSession($firstSession, $user->id);

        $secondSession = LegislativeSession::create([
            'session_date' => now()->addWeeks(2),
            'session_kind' => 'regular',
            'status' => 'draft',
            'prior_session_id' => $firstSession->id,
            'created_by' => $user->id,
        ]);

        $secondDocument = ObDocument::create([
            'legislative_session_id' => $secondSession->id,
            'title' => 'OB 2',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        app(ObDocumentTemplateService::class)->seedDefaultBlocks($secondDocument);
        $lifecycle->syncNewSession($secondSession, $user->id);

        $agenda->refresh();

        $this->assertSame($secondSession->id, $agenda->last_ob_synced_session_id);
        $this->assertTrue(
            ObBlock::query()
                ->where('ob_document_id', $secondDocument->id)
                ->where('agenda_item_id', $agenda->id)
                ->exists()
        );
    }
}
