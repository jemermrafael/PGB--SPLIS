<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\ObDocument;
use App\Models\User;
use App\Services\ObDocumentTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObAgendaPoolTest extends TestCase
{
    use RefreshDatabase;

    public function test_pool_hides_done_lapsed_and_lifecycle_resolved_agendas(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $session = LegislativeSession::query()->create([
            'session_number' => '1',
            'session_kind' => 'regular',
            'session_date' => now()->addDay()->toDateString(),
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB',
            'status' => ObDocument::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);
        app(ObDocumentTemplateService::class)->seedDefaultBlocks($document);

        $open = AgendaItem::query()->create([
            'tracking_no' => '100',
            'title' => 'Open agenda',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $admin->id,
        ]);

        AgendaItem::query()->create([
            'tracking_no' => '165',
            'title' => 'Resolved agenda',
            'status' => AgendaItem::STATUS_NO_DUE_DATE,
            'ob_lifecycle_stage' => AgendaItem::OB_STAGE_RESOLVED,
            'prescribed_days' => 0,
            'created_by' => $admin->id,
        ]);

        AgendaItem::query()->create([
            'tracking_no' => '200',
            'title' => 'Done agenda',
            'status' => AgendaItem::STATUS_DONE,
            'prescribed_days' => 0,
            'created_by' => $admin->id,
        ]);

        AgendaItem::query()->create([
            'tracking_no' => '201',
            'title' => 'Lapsed agenda',
            'status' => AgendaItem::STATUS_LAPSED,
            'prescribed_days' => 0,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('ob.document.agenda-pool', $session))
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$open->id], $ids);
        $this->assertSame(1, $response->json('meta.total'));
    }
}
